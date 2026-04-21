<?php

use App\Support\LogRedactor;

describe('LogRedactor::redactBody', function () {
    it('redacts flat body fields matching the key list', function () {
        $body = ['username' => 'alice', 'password' => 'secret123'];
        $result = LogRedactor::redactBody($body, ['password']);

        expect($result)->toBe(['username' => 'alice', 'password' => '[REDACTED]']);
    });

    it('redacts nested body fields at any depth', function () {
        $body = [
            'user' => [
                'credentials' => [
                    'password' => 'hunter2',
                    'username' => 'bob',
                ],
            ],
        ];
        $result = LogRedactor::redactBody($body, ['password']);

        expect($result['user']['credentials']['password'])->toBe('[REDACTED]');
        expect($result['user']['credentials']['username'])->toBe('bob');
    });

    it('passes unknown keys through untouched', function () {
        $body = ['email' => 'user@example.com', 'name' => 'Alice'];
        $result = LogRedactor::redactBody($body, ['password', 'token']);

        expect($result)->toBe(['email' => 'user@example.com', 'name' => 'Alice']);
    });

    it('is case-insensitive when matching keys', function () {
        $body = ['Password' => 'secret', 'PASSWORD' => 'also-secret'];
        $result = LogRedactor::redactBody($body, ['password']);

        expect($result['Password'])->toBe('[REDACTED]');
        expect($result['PASSWORD'])->toBe('[REDACTED]');
    });

    it('returns non-array values as-is', function () {
        expect(LogRedactor::redactBody('plain string', ['password']))->toBe('plain string');
        expect(LogRedactor::redactBody(42, ['password']))->toBe(42);
        expect(LogRedactor::redactBody(null, ['password']))->toBeNull();
    });
});

describe('LogRedactor::redactHeaders', function () {
    it('drops authorization header from the deny list', function () {
        $headers = [
            'authorization' => ['Bearer token123'],
            'content-type' => ['application/json'],
        ];
        $result = LogRedactor::redactHeaders($headers, ['authorization', 'cookie']);

        expect($result)->not->toHaveKey('authorization');
        expect($result)->toHaveKey('content-type');
    });

    it('drops cookie header from the deny list', function () {
        $headers = ['cookie' => ['session=abc'], 'accept' => ['*/*']];
        $result = LogRedactor::redactHeaders($headers, ['authorization', 'cookie']);

        expect($result)->not->toHaveKey('cookie');
    });

    it('drops x-telegram-bot-api-secret-token from the deny list', function () {
        $headers = [
            'x-telegram-bot-api-secret-token' => ['mysecret'],
            'x-custom' => ['value'],
        ];
        $result = LogRedactor::redactHeaders($headers, ['x-telegram-bot-api-secret-token']);

        expect($result)->not->toHaveKey('x-telegram-bot-api-secret-token');
        expect($result)->toHaveKey('x-custom');
    });

    it('lowercases header names for allowed headers', function () {
        $headers = ['Content-Type' => ['application/json'], 'X-Custom-Header' => ['val']];
        $result = LogRedactor::redactHeaders($headers, []);

        expect($result)->toHaveKey('content-type');
        expect($result)->toHaveKey('x-custom-header');
    });
});
