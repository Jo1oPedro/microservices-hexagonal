<?php

declare(strict_types=1);
namespace App\Tracing\Aspect;

use App\Tracing\Annotation\TraceLayer;
use Hyperf\Context\Context;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Tracer\TracerContext;

class NestedTraceAspect extends AbstractAspect
{
    public array $annotations = [TraceLayer::class];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        /** @var TraceLayer $ann */
        $ann = $proceedingJoinPoint->getAnnotationMetadata()->method[TraceLayer::class];
        $tracer = TracerContext::getTracer();

        $stack = Context::get("tracer.span_stack", []);
        $parent = end($stack) ?: TracerContext::getRoot();

        $span = $tracer->startSpan($ann->name, [
            "child_of" => $parent ? $parent->getContext() : null
        ]);
        $span->setTag(
            $ann->tag ?: "source",
            $proceedingJoinPoint->className."::".$proceedingJoinPoint->methodName
        );

        $previousRoot = TracerContext::getRoot();
        TracerContext::setRoot($parent);

        $stack[] = $span;
        Context::set("tracer.span_stack", $stack);

        try {
            return $proceedingJoinPoint->process();
        } catch (\Throwable $throwable) {
            $span->setTag("error", true);
            $span->log(["message" => $throwable->getMessage()]);
            throw $throwable;
        } finally {
            $span->finish();

            if($previousRoot) {
                TracerContext::setRoot($previousRoot);
            }

            $stack = Context::get("tracer.span_stack", []);
            array_pop($stack);
            Context::set("tracer.span_stack", $stack);
        }
    }
}