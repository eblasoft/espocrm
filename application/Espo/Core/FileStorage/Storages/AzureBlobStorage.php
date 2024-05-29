<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM – Open Source CRM application.
 * Copyright (C) 2014-2024 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Core\FileStorage\Storages;

use Espo\Core\FileStorage\Attachment;
use Espo\Core\FileStorage\Storage;
use Espo\Core\Utils\Config;
use GuzzleHttp\Psr7\Stream;
use League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * @noinspection PhpUnused
 */

class AzureBlobStorage implements Storage
{
    private Filesystem $filesystem;

    public function __construct(Config $config)
    {
        $connectionString = $config->get('azureBlobStorage.connectionString');
        $containerName = $config->get('azureBlobStorage.containerName');
        $prefix = $config->get('azureStorage.prefix') ?? 'uploads';

        $client = BlobRestProxy::createBlobService($connectionString);
        $adapter = new AzureBlobStorageAdapter(
            $client,
            $containerName,
            $prefix,
        );

        $this->filesystem = new Filesystem($adapter);
    }

    public function getStream(Attachment $attachment): StreamInterface
    {
        try {
            $resource = $this->filesystem->readStream($attachment->getSourceId());
        }
        catch (FilesystemException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        return new Stream($resource);
    }

    public function putStream(Attachment $attachment, StreamInterface $stream): void
    {
        $resource = fopen('php://temp', 'r+');

        if ($resource === false) {
            throw new RuntimeException("Could not open temp.");
        }

        $stream->rewind();

        fwrite($resource, $stream->getContents());
        rewind($resource);

        try {
            $this->filesystem->writeStream($attachment->getSourceId(), $resource);
        }
        catch (FilesystemException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    public function exists(Attachment $attachment): bool
    {
        try {
            return $this->filesystem->fileExists($attachment->getSourceId());
        }
        catch (FilesystemException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    public function unlink(Attachment $attachment): void
    {
        try {
            $this->filesystem->delete($attachment->getSourceId());
        }
        catch (FilesystemException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    public function getSize(Attachment $attachment): int
    {
        try {
            return $this->filesystem->fileSize($attachment->getSourceId());
        }
        catch (FilesystemException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }
}
