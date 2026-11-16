<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LogoutEvent::class)]
final class ApiLogoutListener
{
    public function __invoke(LogoutEvent $event) : void
    {
        $attributeRoute = $event->getRequest()->attributes->get('_route');

        if($attributeRoute === 'api_logout'){
            $event->setResponse(new Response(status: Response::HTTP_NO_CONTENT));
        }
    }
}
