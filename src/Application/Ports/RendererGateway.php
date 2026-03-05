<?php
declare(strict_types=1);

namespace App\Application\Ports;

interface RendererGateway
{
    /**
     * @return array{svgPath:string, svg:string, isValid:bool}
     */
    public function renderSvg(int $documentId, int $revision, string $code): array;
}
