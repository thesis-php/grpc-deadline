<?php

declare(strict_types=1);

namespace Thesis\Grpc\Deadline;

use Amp\Cancellation;
use Amp\CompositeCancellation;
use Amp\TimeoutCancellation;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Client\StreamInterceptor;
use Thesis\Grpc\Client\UnaryInterceptor;
use Thesis\Grpc\ClientStream;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Metadata\Timeout;

/**
 * @api
 */
final readonly class ClientInterceptor implements
    UnaryInterceptor,
    StreamInterceptor
{
    public function __construct(
        private ?Timeout $default = null,
    ) {}

    #[\Override]
    public function interceptUnary(
        object $request,
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $invoker,
    ): object {
        [$md, $cancellation] = $this->withDeadline($md, $cancellation);

        return $invoker($request, $invoke, $md, $cancellation);
    }

    #[\Override]
    public function interceptStream(
        Invoke $invoke,
        Metadata $md,
        Cancellation $cancellation,
        callable $newStream,
    ): ClientStream {
        [$md, $cancellation] = $this->withDeadline($md, $cancellation);

        return $newStream($invoke, $md, $cancellation);
    }

    /**
     * @return array{Metadata, Cancellation}
     */
    private function withDeadline(Metadata $md, Cancellation $cancellation): array
    {
        $timeout = Metadata\parseTimeout($md) ?? $this->default;

        if ($timeout === null) {
            return [$md, $cancellation];
        }

        return [
            $md->withKey($timeout),
            new CompositeCancellation($cancellation, new TimeoutCancellation($timeout->toSeconds())),
        ];
    }
}
