<?php
declare(strict_types=1);

namespace App\Infrastructure\Http;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Symfony-ready skeleton.
 * TODO (later): split controllers + add DTO validation + security.
 */

final class AuthController
{
  #[Route('/api/auth/login', methods: ['POST'])]
  public function login(Request $req): JsonResponse
  {
    // TODO: integrate with Symfony Security + password hashing.
    return new JsonResponse(['token' => 'demo-token', 'user' => ['id' => 1, 'name' => 'demo']]);
  }

  #[Route('/api/auth/logout', methods: ['POST'])]
  public function logout(): JsonResponse
  {
    return new JsonResponse(['ok' => true]);
  }
}

final class DocumentController
{
  #[Route('/api/documents/{id}', methods: ['GET'])]
  #[IsGranted('DOC_VIEW')]
  public function get(int $id): JsonResponse
  {
    // TODO: call UseCase GetDocument
    return new JsonResponse(['todo' => true, 'id' => $id]);
  }

  #[Route('/api/documents/{id}/revisions', methods: ['POST'])]
  #[IsGranted('DOC_EDIT')]
  public function saveRevision(int $id): JsonResponse
  {
    // TODO: call SaveRevision usecase + renderer
    return new JsonResponse(['todo' => true, 'id' => $id]);
  }
}

final class SessionController
{
  #[Route('/api/sessions', methods: ['POST'])]
  #[IsGranted('ROLE_USER')]
  public function join(): JsonResponse
  {
    // TODO: call JoinSession usecase
    return new JsonResponse(['todo' => true]);
  }
}

final class QuizController
{
  #[Route('/api/quizzes/random', methods: ['POST'])]
  #[IsGranted('ROLE_USER')]
  public function random(): JsonResponse
  {
    // TODO: call TakeRandomQuiz usecase
    return new JsonResponse(['todo' => true]);
  }

  #[Route('/api/quizzes/{id}/submit', methods: ['POST'])]
  #[IsGranted('ROLE_USER')]
  public function submit(int $id): JsonResponse
  {
    // TODO: call SubmitQuiz usecase
    return new JsonResponse(['todo' => true, 'id' => $id]);
  }
}

final class MeController
{
  #[Route('/api/me/documents', methods: ['GET'])]
  #[IsGranted('ROLE_USER')]
  public function documents(): JsonResponse
  {
    // TODO: call ListPersonalDocs usecase
    return new JsonResponse(['todo' => true]);
  }

  #[Route('/api/me/stats', methods: ['GET'])]
  #[IsGranted('ROLE_USER')]
  public function stats(): JsonResponse
  {
    // TODO: call GetStats usecase
    return new JsonResponse(['todo' => true]);
  }
}
