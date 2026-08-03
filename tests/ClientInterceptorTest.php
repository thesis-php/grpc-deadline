<?php

declare(strict_types=1);

namespace Thesis\Grpc\Deadline;

use Amp\Cancellation;
use Amp\NullCancellation;
use Testo\Assert;
use Testo\Test;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Metadata\Timeout;
use Thesis\Grpc\RpcType;

#[Test]
final class ClientInterceptorTest
{
    public function appliesTheDefaultDeadline(): void
    {
        $original = new NullCancellation();
        $seenMd = null;
        $seenCancellation = null;

        /** @var Invoke<\stdClass, \stdClass> $invoke */
        $invoke = new Invoke('/svc/Method', \stdClass::class, RpcType::Unary);

        new ClientInterceptor(Timeout::seconds(30))->interceptUnary(
            new \stdClass(),
            $invoke,
            new Metadata(),
            $original,
            static function (object $request, Invoke $invoke, Metadata $md, Cancellation $cancellation) use (&$seenMd, &$seenCancellation): \stdClass {
                $seenMd = $md;
                $seenCancellation = $cancellation;

                return new \stdClass();
            },
        );

        Assert::instanceOf($seenMd, Metadata::class);
        Assert::same($seenMd->value('grpc-timeout'), '30S');
        Assert::notSame($seenCancellation, $original);
    }

    public function perCallDeadlineOverridesTheDefault(): void
    {
        $seenMd = null;

        /** @var Invoke<\stdClass, \stdClass> $invoke */
        $invoke = new Invoke('/svc/Method', \stdClass::class, RpcType::Unary);

        new ClientInterceptor(Timeout::seconds(30))->interceptUnary(
            new \stdClass(),
            $invoke,
            new Metadata()->withKey(Timeout::milliseconds(100)),
            new NullCancellation(),
            static function (object $request, Invoke $invoke, Metadata $md, Cancellation $cancellation) use (&$seenMd): \stdClass {
                $seenMd = $md;

                return new \stdClass();
            },
        );

        Assert::instanceOf($seenMd, Metadata::class);
        Assert::same($seenMd->value('grpc-timeout'), '100m');
    }

    public function leavesCallsUntouchedWithoutADeadline(): void
    {
        $original = new NullCancellation();
        $seenMd = null;
        $seenCancellation = null;

        /** @var Invoke<\stdClass, \stdClass> $invoke */
        $invoke = new Invoke('/svc/Method', \stdClass::class, RpcType::Unary);

        new ClientInterceptor()->interceptUnary(
            new \stdClass(),
            $invoke,
            new Metadata(),
            $original,
            static function (object $request, Invoke $invoke, Metadata $md, Cancellation $cancellation) use (&$seenMd, &$seenCancellation): \stdClass {
                $seenMd = $md;
                $seenCancellation = $cancellation;

                return new \stdClass();
            },
        );

        Assert::instanceOf($seenMd, Metadata::class);
        Assert::false($seenMd->has('grpc-timeout'));
        Assert::same($seenCancellation, $original);
    }

    public function appliesTheDeadlineToStreams(): void
    {
        $seenMd = null;

        /** @var Invoke<\stdClass, \stdClass> $invoke */
        $invoke = new Invoke('/svc/Stream', \stdClass::class, RpcType::ServerStream);

        new ClientInterceptor(Timeout::seconds(5))->interceptStream(
            $invoke,
            new Metadata(),
            new NullCancellation(),
            static function (Invoke $invoke, Metadata $md, Cancellation $cancellation) use (&$seenMd): ClientStream {
                $seenMd = $md;

                return new FakeClientStream();
            },
        );

        Assert::instanceOf($seenMd, Metadata::class);
        Assert::same($seenMd->value('grpc-timeout'), '5S');
    }
}

/**
 * @template-implements ClientStream<\stdClass, \stdClass>
 */
final class FakeClientStream implements ClientStream
{
    #[\Override]
    public function send(object $message): void {}

    #[\Override]
    public function receive(): object
    {
        throw new \LogicException('FakeClientStream is not consumed.');
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        yield from [];
    }

    #[\Override]
    public function headers(): Metadata
    {
        return new Metadata();
    }

    #[\Override]
    public function trailers(Cancellation $cancellation = new NullCancellation()): Metadata
    {
        return new Metadata();
    }

    #[\Override]
    public function close(): void {}
}
