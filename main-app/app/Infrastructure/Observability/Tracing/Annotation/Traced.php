<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Tracing\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_METHOD)]
final class Traced extends AbstractAnnotation
{
    public function __construct(
        public ?string $name = null,
        public string $kind = 'internal',
    ) {}
}
