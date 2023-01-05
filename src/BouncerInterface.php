<?php

declare(strict_types=1);

namespace CrowdSecBouncer;

/**
 * The interface to implement when bouncing.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2021+ CrowdSec
 * @license   MIT License
 */
interface BouncerInterface
{
    /**
     * @return string The current IP, even if it's the IP of a proxy
     */
    public function getHttpMethod(): string;

    /**
     * @return string Ex: "X-Forwarded-For"
     */
    public function getHttpRequestHeader(string $name): ?string;

    /**
     * Get the value of a posted field.
     */
    public function getPostedVariable(string $name): ?string;

    /**
     * @return string The current IP, even if it's the IP of a proxy
     */
    public function getRemoteIp(): string;

    /**
     * If there is any technical problem while bouncing, don't block the user. Bypass bouncing and log the error.
     */
    public function safelyBounce(): bool;

    /**
     * Send HTTP response.
     */
    public function sendResponse(?string $body, int $statusCode = 200): void;

    /**
     * If the current IP should be bounced or not, matching custom business rules.
     */
    public function shouldBounceCurrentIp(): bool;
}
