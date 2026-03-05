<?php
declare(strict_types=1);

namespace App\Application;

use App\Application\Ports\DocumentRepository;
use App\Application\Ports\RevisionRepository;
use App\Application\Ports\SessionRepository;
use App\Application\Ports\EventBus;
use App\Application\Ports\RendererGateway;

final class Ports
{
    public function __construct(
        public readonly DocumentRepository $documents,
        public readonly RevisionRepository $revisions,
        public readonly SessionRepository  $sessions,
        public readonly EventBus           $events,
        public readonly RendererGateway    $renderer,
    )
    {
    }
}
