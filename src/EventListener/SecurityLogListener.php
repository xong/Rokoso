<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\SecurityLog;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\RememberMeAuthenticator;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Logs logins (with password, remember-me cookie excluded), failed logins and failed second factors.
 */
final readonly class SecurityLogListener
{
    public function __construct(private SecurityLog $log, private UserRepository $users)
    {
    }

    #[AsEventListener]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User || $event->getAuthenticator() instanceof RememberMeAuthenticator) {
            return;
        }
        // with two-factor login the entry is written once the code was accepted
        if ($event->getAuthenticatedToken() instanceof TwoFactorTokenInterface) {
            return;
        }
        $this->log->record('login', $user, $event->getRequest()->headers->get('User-Agent'));
    }

    #[AsEventListener]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $identifier = $event->getPassport()?->getBadge(UserBadge::class)?->getUserIdentifier();
        $user = null !== $identifier ? $this->users->findOneByEmail($identifier) : null;
        if (null !== $user) {
            $this->log->record('login_failed', $user, $event->getException()->getMessageKey());
        }
    }

    #[AsEventListener(event: TwoFactorAuthenticationEvents::COMPLETE)]
    public function onTwoFactorComplete(TwoFactorAuthenticationEvent $event): void
    {
        $user = $event->getToken()->getUser();
        if ($user instanceof User) {
            $this->log->record('login', $user, $event->getRequest()->headers->get('User-Agent').' (2FA)');
        }
    }

    #[AsEventListener(event: TwoFactorAuthenticationEvents::FAILURE)]
    public function onTwoFactorFailure(TwoFactorAuthenticationEvent $event): void
    {
        $user = $event->getToken()->getUser();
        if ($user instanceof User) {
            $this->log->record('two_factor_failed', $user);
        }
    }
}
