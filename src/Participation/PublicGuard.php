<?php

declare(strict_types=1);

namespace App\Participation;

use App\Entity\PublicHit;
use App\Entity\PublicSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Invisible spam protection for public forms: honeypot field, signed render time with minimum fill-in duration,
 * and throttling per (hashed) IP address. No external service, no cookies.
 */
final readonly class PublicGuard
{
    public const string HONEYPOT = 'website';
    public const string STAMP = 'started';

    public function __construct(
        private EntityManagerInterface $em,
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    /**
     * Adds the honeypot and the signed render time to a public form.
     *
     * @param FormBuilderInterface<mixed> $builder
     */
    public function addFields(FormBuilderInterface $builder): void
    {
        $builder
            ->add(self::HONEYPOT, TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'public.honeypot',
                'row_attr' => ['class' => 'hp-field', 'aria-hidden' => 'true'],
                'attr' => ['tabindex' => '-1', 'autocomplete' => 'off'],
            ])
            ->add(self::STAMP, HiddenType::class, ['mapped' => false, 'data' => $this->stamp()]);
    }

    public function stamp(?int $time = null): string
    {
        $time ??= time();

        return $time.'.'.substr(hash_hmac('sha256', 'public-form'.$time, $this->secret), 0, 24);
    }

    /**
     * Checks a submitted form; records the attempt. Returns a translation key on rejection, null when accepted.
     *
     * @param FormInterface<mixed> $form
     */
    public function check(PublicSettings $settings, FormInterface $form, ?string $ip): ?string
    {
        if (!$settings->isSpamInvisible()) {
            return null;
        }
        if ('' !== trim((string) $form->get(self::HONEYPOT)->getData())) {
            return 'public.error.rejected';
        }

        $stamp = (string) $form->get(self::STAMP)->getData();
        $time = (int) strstr($stamp, '.', true);
        if (!hash_equals($this->stamp($time), $stamp) || $time < time() - 86400) {
            return 'public.error.expired';
        }
        if (time() - $time < $settings->getSpamMinSeconds()) {
            return 'public.error.too_fast';
        }

        $hash = hash_hmac('sha256', (string) $ip, $this->secret);
        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(h.id)')
            ->from(PublicHit::class, 'h')
            ->where('h.organization = :org AND h.ipHash = :hash AND h.createdAt > :since')
            ->setParameter('org', $settings->getOrganization())
            ->setParameter('hash', $hash)
            ->setParameter('since', new \DateTimeImmutable('-1 hour'))
            ->getQuery()->getSingleScalarResult();
        if ($count >= $settings->getSpamMaxPerHour()) {
            return 'public.error.throttled';
        }

        $this->em->persist(new PublicHit($settings->getOrganization(), $hash));

        return null;
    }
}
