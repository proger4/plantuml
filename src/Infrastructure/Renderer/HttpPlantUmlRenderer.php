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
        'ignore_errors' => true,
      ],
    ]);

    $svg = @file_get_contents($this->endpoint, false, $context);
    $headers = function_exists('http_get_last_response_headers')
      ? (http_get_last_response_headers() ?: [])
      : [];
    $status = $this->extractHttpStatus($headers);
    if ($svg === false || !is_string($svg) || !str_contains($svg, '<svg')) {
      $reason = error_get_last()['message'] ?? 'renderer_unreachable';
      $errorSvg = $this->errorSvg($documentId, $revision, $reason);
      $path = rtrim($this->renderDir, '/') . "/doc_{$documentId}_rev_{$revision}.svg";
      file_put_contents($path, $errorSvg);
      return [
        'svgPath' => $path,
        'svg' => $errorSvg,
        'isValid' => false,
      ];
    }

    $path = rtrim($this->renderDir, '/') . "/doc_{$documentId}_rev_{$revision}.svg";
    file_put_contents($path, $svg);

    return [
      'svgPath' => $path,
      'svg' => $svg,
      'isValid' => $status >= 200 && $status < 300,
    ];
  }

  private function extractHttpStatus(array $headers): int
  {
    $status = 0;
    if (isset($headers[0]) && is_string($headers[0])) {
      if (preg_match('/\s(\d{3})\s/', $headers[0], $m)) {
        $status = (int)$m[1];
      }
    }
    return $status;
  }

  private function errorSvg(int $documentId, int $revision, string $reason): string
  {
    $safe = htmlspecialchars($reason, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="900" height="180">
  <rect x="0" y="0" width="100%" height="100%" fill="#fff7ed"/>
  <text x="20" y="36" font-family="monospace" font-size="16" fill="#b45309">PlantUML renderer unavailable</text>
  <text x="20" y="62" font-family="monospace" font-size="12" fill="#92400e">doc={$documentId} rev={$revision}</text>
  <text x="20" y="90" font-family="monospace" font-size="12" fill="#92400e">{$safe}</text>
</svg>
SVG;
  }
}
