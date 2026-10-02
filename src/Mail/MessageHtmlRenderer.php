<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Message;
use App\Service\AttachmentStorage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Builds a standalone, sanitized HTML document for the sandboxed mail iframe.
 * Inline images (cid:) are embedded as data: URIs; external images are blocked by CSP unless allowed.
 */
final readonly class MessageHtmlRenderer
{
    private const int MAX_INLINE_IMAGE = 2_000_000;

    public function __construct(
        #[Autowire(service: 'html_sanitizer.sanitizer.app.mail_sanitizer')]
        private HtmlSanitizerInterface $sanitizer,
        private AttachmentStorage $storage,
    ) {
    }

    public function render(Message $message): string
    {
        $html = (string) $message->getHtmlBody();

        foreach ($message->getAttachments() as $attachment) {
            if (!$attachment->isInline() || $attachment->getSize() > self::MAX_INLINE_IMAGE) {
                continue;
            }
            $dataUri = 'data:'.$attachment->getMimeType().';base64,'.base64_encode($this->storage->read($attachment->getStoragePath()));
            $html = str_ireplace('cid:'.$attachment->getContentId(), $dataUri, $html);
        }

        $body = $this->sanitizer->sanitize($html);

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><base target="_blank">'
            .'<style>body{margin:0;padding:16px;font:14px/1.5 system-ui,sans-serif;color:#0f172a;word-wrap:break-word}img{max-width:100%;height:auto}table{max-width:100%}</style>'
            .'</head><body>'.$body.'</body></html>';
    }

    public function contentSecurityPolicy(bool $allowExternalImages): string
    {
        return "default-src 'none'; style-src 'unsafe-inline'; img-src data:".($allowExternalImages ? ' https: http:' : '').'; base-uri \'none\'; form-action \'none\'';
    }

    /**
     * Whether the HTML references external images (to offer "load images").
     */
    public function hasExternalImages(Message $message): bool
    {
        return 1 === preg_match('#<img[^>]+src\s*=\s*["\']?https?://#i', (string) $message->getHtmlBody());
    }
}
