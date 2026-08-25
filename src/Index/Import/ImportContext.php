<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index\Import;

use SeviaFlow\Core\Resources\ImportResource;
use SeviaFlow\Core\Resources\Resource;

final class ImportContext
{
    /**
     * @param  class-string<resource>  $resourceClass
     */
    public function canImport(string $resourceClass, bool $canCreate): bool
    {
        $import = $this->definition($resourceClass);

        return $import->isEnabled()
            && $import->getAllowedFields() !== []
            && $canCreate;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @return array<int, string>
     */
    public function fields(string $resourceClass): array
    {
        return $this->definition($resourceClass)->getAllowedFields();
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @return array<int, string>
     */
    public function requiredFields(string $resourceClass): array
    {
        return $this->definition($resourceClass)->getRequiredFields();
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    public function definition(string $resourceClass): ImportResource
    {
        return $resourceClass::import(new ImportResource);
    }
}
