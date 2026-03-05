SHELL := /bin/zsh

HTTP_HOST ?= 127.0.0.1
HTTP_PORT ?= 8000
WS_HOST ?= 127.0.0.1
WS_PORT ?= 8081

RUN_DIR := var/run
LOG_DIR := var/log
HTTP_PID := $(RUN_DIR)/http.pid
WS_PID := $(RUN_DIR)/ws.pid
HTTP_LOG := $(LOG_DIR)/http.log
WS_LOG := $(LOG_DIR)/ws.log

.PHONY: help deps deps-soft migrate start start-http start-ws stop stop-http stop-ws status logs renderer-up renderer-down test-backend

help:
	@echo "make start        # migrate DB + start backend (HTTP + WS)"
	@echo "make stop         # stop backend (HTTP + WS)"
	@echo "make status       # show backend process status"
	@echo "make logs         # tail backend logs"
	@echo "make deps         # install composer dependencies if needed"
	@echo "make migrate      # run SQL migrations"
	@echo "make renderer-up  # start PlantUML renderer docker service"
	@echo "make renderer-up-soft # try start renderer, but do not fail"
	@echo "make test-backend # run backend smoke tests"

deps:
	@if [ -f vendor/autoload.php ]; then \
		echo "Composer deps already installed"; \
	else \
		echo "Installing composer dependencies..."; \
		composer install --no-interaction --prefer-dist; \
	fi

deps-soft:
	@if [ -f vendor/autoload.php ]; then \
		echo "Composer deps already installed"; \
	else \
		echo "Trying to install composer dependencies..."; \
		if composer install --no-interaction --prefer-dist; then \
			echo "Composer dependencies installed"; \
		else \
			echo "WARNING: composer install failed; HTTP will run in local-autoload mode, WS disabled"; \
		fi; \
	fi

migrate:
	php bin/migrate.php

start: renderer-up-soft migrate start-http start-ws status

start-http:
	@mkdir -p $(RUN_DIR) $(LOG_DIR)
	@if [ -f $(HTTP_PID) ] && kill -0 $$(cat $(HTTP_PID)) 2>/dev/null; then \
		echo "HTTP already running (pid=$$(cat $(HTTP_PID)))"; \
	else \
		nohup php -S $(HTTP_HOST):$(HTTP_PORT) index.php > $(HTTP_LOG) 2>&1 & echo $$! > $(HTTP_PID); \
		echo "HTTP started: http://$(HTTP_HOST):$(HTTP_PORT) (pid=$$(cat $(HTTP_PID)))"; \
	fi

start-ws:
	@mkdir -p $(RUN_DIR) $(LOG_DIR)
	@if [ ! -f vendor/autoload.php ]; then \
		echo "WS skipped: vendor/autoload.php missing (run make deps when network is available)"; \
	elif [ -f $(WS_PID) ] && kill -0 $$(cat $(WS_PID)) 2>/dev/null; then \
		echo "WS already running (pid=$$(cat $(WS_PID)))"; \
	else \
		nohup php ws-server.php > $(WS_LOG) 2>&1 & echo $$! > $(WS_PID); \
		echo "WS started: ws://$(WS_HOST):$(WS_PORT) (pid=$$(cat $(WS_PID)))"; \
	fi

stop: stop-http stop-ws

stop-http:
	@if [ -f $(HTTP_PID) ] && kill -0 $$(cat $(HTTP_PID)) 2>/dev/null; then \
		kill $$(cat $(HTTP_PID)); \
		rm -f $(HTTP_PID); \
		echo "HTTP stopped"; \
	else \
		echo "HTTP is not running"; \
	fi

stop-ws:
	@if [ -f $(WS_PID) ] && kill -0 $$(cat $(WS_PID)) 2>/dev/null; then \
		kill $$(cat $(WS_PID)); \
		rm -f $(WS_PID); \
		echo "WS stopped"; \
	else \
		echo "WS is not running"; \
	fi

status:
	@echo "HTTP:"; \
	if [ -f $(HTTP_PID) ] && kill -0 $$(cat $(HTTP_PID)) 2>/dev/null; then \
		echo "  running (pid=$$(cat $(HTTP_PID))) http://$(HTTP_HOST):$(HTTP_PORT)"; \
	else \
		echo "  stopped"; \
	fi
	@echo "WS:"; \
	if [ -f $(WS_PID) ] && kill -0 $$(cat $(WS_PID)) 2>/dev/null; then \
		echo "  running (pid=$$(cat $(WS_PID))) ws://$(WS_HOST):$(WS_PORT)"; \
	else \
		echo "  stopped"; \
	fi

logs:
	@mkdir -p $(LOG_DIR)
	@touch $(HTTP_LOG) $(WS_LOG)
	tail -n 50 -f $(HTTP_LOG) $(WS_LOG)

renderer-up:
	docker compose up -d plantuml-renderer

renderer-up-soft:
	@echo "Starting renderer (best-effort)..."
	@docker compose up -d plantuml-renderer || echo "WARNING: renderer was not started"

renderer-down:
	docker compose stop plantuml-renderer

test-backend: migrate renderer-up-soft
	./test/smoke_backend.sh
