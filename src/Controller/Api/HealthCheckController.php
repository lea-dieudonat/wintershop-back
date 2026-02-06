<?php

namespace App\Controller\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HealthCheckController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection
    ) {}

    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function check(): JsonResponse
    {
        try {
            // Test database connection
            $this->connection->executeQuery('SELECT 1');

            return new JsonResponse([
                'status' => 'healthy',
                'database' => 'connected',
                'timestamp' => date('c')
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'unhealthy',
                'database' => 'disconnected',
                'error' => $e->getMessage(),
                'timestamp' => date('c')
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
