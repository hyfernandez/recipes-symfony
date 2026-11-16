<?php

namespace App\Controller\Api;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ApiLoginController extends AbstractController
{
    /**
     * POST {"email": "...", "password": "..."}. The json_login authenticator of the "api" firewall
     * checks the credentials and starts the session (the cookie comes back in Set-Cookie). With
     * valid credentials it lets the request continue to this method, which answers who is logged in.
     * Bad credentials never get here: the authenticator answers 401 itself.
     */
    #[Route('/api/login', name: 'api_login', methods: ['POST'], defaults: ['_format' => 'json'])]
    public function login(#[CurrentUser] ?User $user): JsonResponse
    {
        if (null === $user) {
            return $this->json(['error' => 'Missing credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json(['email' => $user->getEmail(), 'roles' => $user->getRoles()]);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'], defaults: ['_format' => 'json'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => 'Not authenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json(['email' => $user->getEmail(), 'roles' => $user->getRoles()]);
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'], defaults: ['_format' => 'json'])]
    public function logout() : JsonResponse {
        throw new \LogicException('This method is intercepted by the logout key of the firewall.');
    }
}
