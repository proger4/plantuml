<?php
declare(strict_types=1);

namespace App\Infrastructure\Renderer;

use App\Application\Ports\RendererGateway;

/**
 * Stub renderer: returns SVG placeholder and stores it under var/renders.
 * TODO (later): replace with real PlantUML server integration.
 * Ref: https://plantuml.com/server
 */
final class StubPlantUmlRenderer implements RendererGateway
{
    public function __construct(private string $renderDir)
    {
        if (!is_dir($this->renderDir)) {
            mkdir($this->renderDir, 0777, true);
        }
    }

    public function renderSvg(int $documentId, int $revision, string $code): array
    {
        $safe = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="900" height="420">
  <rect x="0" y="0" width="100%" height="100%" fill="#fff"/>
  <text x="20" y="30" font-family="monospace" font-size="14">PlantUML Renderer Stub</text>
  <text x="20" y="55" font-family="monospace" font-size="12">doc={$documentId} rev={$revision}</text>
  <foreignObject x="20" y="80" width="860" height="320">
    <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:monospace;font-size:12px;white-space:pre-wrap;">
      {$safe}
    </div>
  </foreignObject>
</svg>
SVG;

        $path = rtrim($this->renderDir, '/') . "/doc_{$documentId}_rev_{$revision}.svg";
        file_put_contents($path, $svg);

        return [
            'svgPath' => $path,
            'svg' => $svg,
            'isValid' => true,
        ];
    }
}
