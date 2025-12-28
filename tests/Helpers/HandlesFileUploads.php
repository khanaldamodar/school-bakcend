<?php

namespace Tests\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HandlesFileUploads
{
    /**
     * Create a fake image file.
     */
    protected function createFakeImage(string $name = 'test.jpg', int $size = 100): UploadedFile
    {
        return UploadedFile::fake()->image($name, $size);
    }

    /**
     * Create a fake document file.
     */
    protected function createFakeDocument(string $name = 'test.pdf', int $size = 1000): UploadedFile
    {
        return UploadedFile::fake()->create($name, $size);
    }

    /**
     * Create a fake CSV file.
     */
    protected function createFakeCsv(string $name = 'test.csv', array $data = []): UploadedFile
    {
        $content = $this->generateCsvContent($data);
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    /**
     * Setup fake storage for testing.
     */
    protected function setupFakeStorage(string $disk = 'public'): void
    {
        Storage::fake($disk);
    }

    /**
     * Assert file exists in storage.
     */
    protected function assertFileExistsInStorage(string $path, string $disk = 'public'): void
    {
        $this->assertTrue(Storage::disk($disk)->exists($path), "File {$path} does not exist in storage.");
    }

    /**
     * Assert file does not exist in storage.
     */
    protected function assertFileNotExistsInStorage(string $path, string $disk = 'public'): void
    {
        $this->assertFalse(Storage::disk($disk)->exists($path), "File {$path} exists in storage but shouldn't.");
    }

    /**
     * Generate CSV content from array data.
     */
    private function generateCsvContent(array $data): string
    {
        if (empty($data)) {
            return "header1,header2,header3\nvalue1,value2,value3\n";
        }

        $content = '';
        $headers = array_keys($data[0] ?? []);
        
        if (!empty($headers)) {
            $content .= implode(',', $headers) . "\n";
        }
        
        foreach ($data as $row) {
            $content .= implode(',', $row) . "\n";
        }
        
        return $content;
    }

    /**
     * Create a large fake file for testing upload limits.
     */
    protected function createLargeFile(string $name = 'large.jpg', int $sizeKb = 5000): UploadedFile
    {
        return UploadedFile::fake()->create($name, $sizeKb);
    }

    /**
     * Create a fake file with invalid extension.
     */
    protected function createInvalidFile(string $name = 'test.exe', int $size = 100): UploadedFile
    {
        return UploadedFile::fake()->create($name, $size);
    }
}