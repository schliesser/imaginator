<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\Lqip;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Lqip\DominantColorGenerator;
use Schliesser\Imaginator\Lqip\LqipGeneratorFactory;
use Schliesser\Imaginator\Lqip\NullLqipGenerator;
use Schliesser\Imaginator\Lqip\ThumbHashGenerator;

final class LqipGeneratorFactoryTest extends TestCase
{
    private function factory(): LqipGeneratorFactory
    {
        return new LqipGeneratorFactory(
            new ThumbHashGenerator(),
            new DominantColorGenerator(),
            new NullLqipGenerator(),
        );
    }

    public function testReturnsThumbHashForThumbhash(): void
    {
        self::assertInstanceOf(ThumbHashGenerator::class, $this->factory()->get('thumbhash'));
    }

    public function testReturnsDominantColorForDominantColor(): void
    {
        self::assertInstanceOf(DominantColorGenerator::class, $this->factory()->get('dominant-color'));
    }

    public function testReturnsNullGeneratorForNone(): void
    {
        self::assertInstanceOf(NullLqipGenerator::class, $this->factory()->get('none'));
    }

    public function testReturnsNullGeneratorForUnknown(): void
    {
        self::assertInstanceOf(NullLqipGenerator::class, $this->factory()->get('totally-unknown'));
    }

    public function testNullGeneratorProducesNull(): void
    {
        self::assertNull((new NullLqipGenerator())->generate(
            $this->createStub(\TYPO3\CMS\Core\Resource\FileInterface::class)
        ));
    }
}
