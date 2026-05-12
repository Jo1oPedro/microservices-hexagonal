<?php

namespace App\Tracing\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_METHOD)]
class TraceLayer extends AbstractAnnotation
{
    public function __construct(
        public string $name,
        public string $tag = "layer"
    ) {}
}