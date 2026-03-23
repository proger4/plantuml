# Roo локальные примеры автоматизации

В этой папке собраны детерминированные сценарии, которые можно сразу запускать локально без внешних API.

## Что внутри

1. **Чисто CLI (детерминированный)**
   - Команда: `commands/fix-copyright.md`
   - Скрипт: `scripts/fix_copyright.py`
   - Когда использовать: нужна предсказуемая правка одного PHP-файла без участия LLM в редактировании.

2. **Custom mode + CLI**
   - Режим: `modes/php-header-fixer.yaml`
   - Когда использовать: хотите закрепить правило "меняем код только скриптами".

3. **Гибрид (LLM анализирует, CLI применяет)**
   - Команда: `commands/fix-copyright-smart.md`
   - Скрипт: `scripts/fix_copyright_smart.py`
   - Когда использовать: стратегия выбирается на основе анализа файла, но изменение остаётся детерминированным.

4. **Валидатор/сканер**
   - Команда: `commands/php-scan.md`
   - Скрипты: `scripts/php_scan.py`, `scripts/verify_copyright.py`
   - Когда использовать: нужно проверить покрытие по проекту и найти проблемы в заголовках.

Дополнительно:
- `commands/explain-file.md` — анализ структуры PHP-файла без правок (declare/namespace/copyright).
- `modes/php-analyzer.yaml` — отдельный read-only режим под анализ.

## Требования

- Python 3.10+
- Только стандартная библиотека Python

## Как запускать

Из корня проекта:

```bash
python3 .roo/scripts/fix_copyright.py "index.php"
python3 .roo/scripts/fix_copyright_smart.py "index.php" "after_open_tag"
python3 .roo/scripts/php_scan.py "."
python3 .roo/scripts/verify_copyright.py "index.php"
```

Через Roo-команды:

```text
/fix-copyright index.php
/fix-copyright-smart index.php
/php-scan .
/explain-file index.php
```

## Поведение скриптов

- `fix_copyright.py`
  - ищет первый подходящий `.php` файл;
  - вставляет copyright после `declare(strict_types=1);` или после `<?php`;
  - вывод: `UPDATED: <path>`, `OK: already present`, `ERROR: not found`.

- `fix_copyright_smart.py`
  - принимает стратегию `after_open_tag` или `after_declare`;
  - строго следует переданной стратегии и не выбирает её сам.

- `php_scan.py`
  - сканирует PHP-файлы в указанной области;
  - выводит статусы: `OK`, `MISSING`, `BROKEN`.
