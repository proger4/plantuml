<?php
declare(strict_types=1);

use App\Collaborations\SessionManager;
use App\DocumentApplier\DocumentApplier;
use App\Domain\Policy\BasicDocumentRestrictions;
use App\Infrastructure\Db\Sqlite;
use App\Infrastructure\Event\InMemoryEventBus;
use App\Infrastructure\Renderer\HttpPlantUmlRenderer;
use App\Infrastructure\Renderer\StubPlantUmlRenderer;
use App\Infrastructure\Repository\PdoRepositories;
use App\Infrastructure\Ws\CollabServer;
use App\Application\UseCases;
use App\Application\Ports;

/**
 * Tiny "container" returning ready-to-use services.
 * TODO (later): replace with Symfony DI container services.yaml / autowire.
 */
return (function (): array {
  $db = new Sqlite(dirname(__DIR__) . '/var/app.sqlite');

  $repos = new PdoRepositories($db->pdo());
  $eventBus = new InMemoryEventBus();
  $renderDir = dirname(__DIR__) . '/var/renders';
  $rendererEndpoint = getenv('PLANTUML_RENDERER_URL') ?: 'http://127.0.0.1:8082/svg';
  $renderer = new HttpPlantUmlRenderer($rendererEndpoint, $renderDir);
  $fallbackRenderer = new StubPlantUmlRenderer($renderDir);

  $restrictions = new BasicDocumentRestrictions($repos->documents());

  $applier = new DocumentApplier();
  $sessionManager = new SessionManager();

  $ports = new Ports(
    $repos->documents(),
    $repos->revisions(),
    $repos->sessions(),
    $eventBus,
    new class($renderer, $fallbackRenderer) implements \App\Application\Ports\RendererGateway {
      public function __construct(
        private HttpPlantUmlRenderer $real,
        private StubPlantUmlRenderer $fallback
      ) {}

      public function renderSvg(int $documentId, int $revision, string $code): array
      {
        $result = $this->real->renderSvg($documentId, $revision, $code);
        if (($result['isValid'] ?? false) === true) {
          return $result;
        }
        // If renderer service is unavailable, keep app usable and mark via SVG stub.
        return $this->fallback->renderSvg($documentId, $revision, $code);
      }
    }
  );

  $useCases = new UseCases($ports, $restrictions, $sessionManager, $applier);

  $ws = new CollabServer($useCases, $sessionManager);

  return [
    'db' => $db,
    'repos' => $repos,
    'ports' => $ports,
    'useCases' => $useCases,
    'ws' => $ws,
  ];
})();
