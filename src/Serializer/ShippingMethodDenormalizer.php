<?php

namespace App\Serializer;

use App\Enum\ShippingMethod;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

class ShippingMethodDenormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (!is_string($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType(
                sprintf('The data for "%s" must be a string.', $context['field_name'] ?? 'shipping_method'),
                $data,
                ['string'],
                $context['deserialization_path'] ?? ''
            );
        }

        try {
            return ShippingMethod::from($data);
        } catch (\ValueError $e) {
            throw NotNormalizableValueException::createForUnexpectedDataType(
                sprintf('The value "%s" is not a valid shipping method.', $data),
                $data,
                ['string'],
                $context['deserialization_path'] ?? ''
            );
        }
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === ShippingMethod::class && is_string($data);
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): string
    {
        if (!$data instanceof ShippingMethod) {
            throw new \InvalidArgumentException('Object must be an instance of ShippingMethod');
        }

        return $data->value;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ShippingMethod;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ShippingMethod::class => true];
    }
}
