<?php

declare(strict_types=1);

namespace Reactiph\Runtime;

use Reactiph\Component\BaseComponent;

/**
 * Builds a {@see HydrationPayload} from a rendered component and serializes
 * a set of payloads into a `<script type="application/json">` tag embedded
 * in the page, for a client-side hydration script to read.
 */
final class HydrationSerializer
{
    /**
     * Properties that are Reactiph plumbing, not meaningful component
     * state, and so are excluded from a serialized payload.
     */
    private const INTERNAL_PROPERTIES = ['slot', 'hydrationId'];

    public static function payloadFor(BaseComponent $component): HydrationPayload
    {
        $hydrationId = $component->hydrationId;

        if ($hydrationId === null) {
            throw MissingHydrationIdException::forComponent($component::class);
        }

        $state = get_object_vars($component);

        foreach (self::INTERNAL_PROPERTIES as $internal) {
            unset($state[$internal]);
        }

        return new HydrationPayload($hydrationId, $component::class, $state);
    }

    /**
     * @param HydrationPayload[] $payloads
     */
    public static function toScriptTag(array $payloads): string
    {
        $data = array_map(
            static fn (HydrationPayload $payload): array => [
                'id' => $payload->id,
                'component' => $payload->component,
                'state' => $payload->state,
            ],
            $payloads,
        );

        // JSON_HEX_TAG (plus the other HEX_* flags) escapes "<", ">", "&",
        // "'" and '"' as \u-sequences, which is what makes it safe to embed
        // this JSON directly inside a <script> tag: without it, component
        // state containing the literal string "</script>" would prematurely
        // close the tag — a real injection vector, not a hypothetical one.
        $json = json_encode(
            $data,
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        );

        return '<script type="application/json" id="reactiph-hydration">' . $json . '</script>';
    }
}
