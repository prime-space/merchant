<?php namespace App\TagServiceProvider;

class TagServiceProvider
{
    public function get(iterable $services, string $name)
    {
        /** @var TagServiceInterface[] $services */
        foreach ($services as $service) {
            if ($name === $service->getTagServiceName()) {
                return $service;
            }
        }

        return null;
    }
}
