<?php

namespace App\Booking\UI\Http;

use App\Booking\Application\Command\UpdateBookingStatus\UpdateBookingStatusCommand;
use App\Booking\Application\Command\UpdateBookingStatus\UpdateBookingStatusHandler;
use App\Service\ValidationErrorFormatter;
use App\Shared\Http\ApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UpdateBookingStatusController extends AbstractController
{
    #[Route('/api/booking/{id}/status', name: 'booking_update_status', methods: ['PATCH'])]
    public function __invoke(string $id, Request $request, UpdateBookingStatusHandler $handler, ValidatorInterface $validator): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getId')) {
            throw new AuthenticationException();
        }

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ApiResponse::error('Invalid JSON');
        }

        $status = $data['status'] ?? null;
        if (!$status) {
            return ApiResponse::error('status is required');
        }

        $command = new UpdateBookingStatusCommand($id, (string) $status);
        $violations = $validator->validate($command);
        if (count($violations) > 0) {
            return ApiResponse::validation(ValidationErrorFormatter::toArray($violations));
        }

        try {
            $handler($command, (string) $user->getId());
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }

        return ApiResponse::success(['status' => 'ok']);
    }
}
