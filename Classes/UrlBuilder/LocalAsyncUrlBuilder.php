<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\UrlBuilder;

use Schliesser\Imaginator\Dto\CanonicalParams;

/**
 * Builds and verifies the HMAC-signed `/_imaginator/…` endpoint URL used by the `local:async`
 * processor (the only path that signs). The name mirrors that processor so the relation is obvious.
 */
final class LocalAsyncUrlBuilder
{
    private const PREFIX = '/_imaginator';
    private const SIG_LEN = 16;

    public function __construct(private readonly string $secret)
    {
        if ($this->secret === '') {
            throw new \InvalidArgumentException('A signing secret is required', 1717600100);
        }
    }

    public function build(CanonicalParams $p): string
    {
        return sprintf(
            '%s/%s/%s%d/%s/%dx%d.%s',
            self::PREFIX,
            $this->sign($p),
            $p->isReference ? 'r' : 'f',
            $p->uid,
            rawurlencode($p->cropVariant),
            $p->width,
            $p->height,
            $p->format,
        );
    }

    public function verify(string $path): ?CanonicalParams
    {
        $pattern = '#^' . preg_quote(self::PREFIX, '#')
            . '/([0-9a-f]{' . self::SIG_LEN . '})/([rf])(\d+)/([^/]+)/(\d+)x(\d+)\.([a-z0-9]+)$#';
        if (!preg_match($pattern, $path, $m)) {
            return null;
        }
        $params = new CanonicalParams(
            $m[2] === 'r',
            (int) $m[3],
            rawurldecode($m[4]),
            (int) $m[5],
            (int) $m[6],
            $m[7],
        );
        return hash_equals($this->sign($params), $m[1]) ? $params : null;
    }

    private function sign(CanonicalParams $p): string
    {
        return substr(hash_hmac('sha256', $p->canonicalString(), $this->secret), 0, self::SIG_LEN);
    }
}
