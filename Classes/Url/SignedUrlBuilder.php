<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Url;

final class SignedUrlBuilder
{
    private const PREFIX = '/_imaginator';
    private const SIG_LEN = 16;

    /** @param list<string> $secrets index 0 is the active signing key; rest are still-valid */
    public function __construct(private readonly array $secrets)
    {
        if ($this->secrets === []) {
            throw new \InvalidArgumentException('At least one signing secret is required', 1717600100);
        }
    }

    public function build(CanonicalParams $p): string
    {
        return sprintf(
            '%s/%s/%d-%d/%s/%dx%d.%s',
            self::PREFIX,
            $this->sign($p, $this->secrets[0]),
            $p->storageUid,
            $p->fileUid,
            rawurlencode($p->cropVariant),
            $p->width,
            $p->height,
            $p->format,
        );
    }

    public function verify(string $path): ?CanonicalParams
    {
        $pattern = '#^' . preg_quote(self::PREFIX, '#')
            . '/([0-9a-f]{' . self::SIG_LEN . '})/(\d+)-(\d+)/([^/]+)/(\d+)x(\d+)\.([a-z0-9]+)$#';
        if (!preg_match($pattern, $path, $m)) {
            return null;
        }
        $params = new CanonicalParams(
            (int)$m[2],
            (int)$m[3],
            rawurldecode($m[4]),
            (int)$m[5],
            (int)$m[6],
            $m[7],
        );
        foreach ($this->secrets as $secret) {
            if (hash_equals($this->sign($params, $secret), $m[1])) {
                return $params;
            }
        }

        return null;
    }

    private function sign(CanonicalParams $p, string $secret): string
    {
        return substr(hash_hmac('sha256', $p->canonicalString(), $secret), 0, self::SIG_LEN);
    }
}
