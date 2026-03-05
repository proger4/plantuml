<?php
declare(strict_types=1);

namespace App\Infrastructure\Renderer;

use App\Application\Ports\RendererGateway;

/**
 * Real PlantUML renderer using the dockerized PlantUML server.
 */
final class HttpPlantUmlRenderer implements RendererGateway
{
  public function __construct(
    private string $endpoint,
    private string $renderDir,
    private int $timeoutSec = 8
  ) {
    if (!is_dir($this->renderDir)) {
      mkdir($this->renderDir, 0777, true);
    }
  }

  public function renderSvg(int $documentId, int $revision, string $code): array
  {
    $context = stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' => "Content-Type: text/plain; charset=utf-8\r\nAccept: image/svg+xml\r\n",
        'content' => $code,
        'timeout' => $this->timeoutSec,
      ],
    ]);

    $svg = @file_get_contents($this->endpoint, false, $context);
    if ($svg === false) {
      return [
        'svgPath' => '',
        'svg' => '',
        'isValid' => false,
      ];
    }

    $path = rtrim($this->renderDir, '/') . "/doc_{$documentId}_rev_{$revision}.svg";
    file_put_contents($path, $svg);

    return [
      'svgPath' => $path,
      'svg' => $svg,
      'isValid' => true,
    ];
  }
}
