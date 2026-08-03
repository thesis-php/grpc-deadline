# thesis/grpc-deadline

Client-side deadline interceptor for [thesis/grpc](https://github.com/thesis-php/grpc). It gives every
outgoing call a deadline and applies it in two places so the client and server agree on it:

- the **`grpc-timeout` header**, which the server enforces (cancelling the RPC and returning
  `DEADLINE_EXCEEDED`);
- a **local `TimeoutCancellation`**, so the client gives up on its own even if the server never
  responds (a plain client deadline otherwise never reaches the server — the transport does not set
  `grpc-timeout` by itself).

## Contents

- [Installation](#installation)
- [Usage](#usage)

## Installation

```bash
composer require thesis/grpc-deadline
```

## Usage

Register the interceptor on the client's unary and stream chains. A default deadline covers every call:

```php
use Thesis\Grpc\Client;
use Thesis\Grpc\Deadline;
use Thesis\Grpc\Metadata\Timeout;

$deadline = new Deadline\ClientInterceptor(Timeout::seconds(30));

$client = new Client\Builder()
    ->withUnaryInterceptors($deadline)
    ->withStreamInterceptors($deadline)
    ->build();
```

Override it per call by attaching a `Timeout` to the call's metadata — the call's own deadline wins
over the default:

```php
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Metadata\Timeout;

$client->invoke($request, $invoke, new Metadata()->withKey(Timeout::milliseconds(200)));
```

Constructed with no default (`new Deadline\ClientInterceptor()`), the interceptor only acts on calls
that carry their own `grpc-timeout`, and leaves the rest untouched.
