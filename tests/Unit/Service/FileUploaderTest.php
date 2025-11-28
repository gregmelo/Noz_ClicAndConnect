<?php

namespace App\Tests\Unit\Service;

use App\Service\FileUploader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\UnicodeString;

class FileUploaderTest extends TestCase
{
    public function testUpload(): void
    {
        $targetDirectory = sys_get_temp_dir();
        $slugger = $this->createMock(SluggerInterface::class);
        
        // Mock the slugger to return a predictable slug
        $slugger->expects($this->once())
            ->method('slug')
            ->willReturn(new UnicodeString('safe-filename'));

        $fileUploader = new FileUploader($targetDirectory, $slugger);

        // Create a mock UploadedFile
        // We can't easily mock the move() method of UploadedFile because it's final or hard to mock without a real file.
        // However, for a unit test of the service logic (naming), we can try to mock it if possible, 
        // or we can use a real temporary file.
        
        // Let's try to mock the UploadedFile to verify it calls move() with correct arguments.
        $file = $this->createMock(UploadedFile::class);
        $file->expects($this->any())
            ->method('getClientOriginalName')
            ->willReturn('Original Name.jpg');
        
        $file->expects($this->any())
            ->method('guessExtension')
            ->willReturn('jpg');

        $file->expects($this->once())
            ->method('move')
            ->with(
                $this->equalTo($targetDirectory),
                $this->stringContains('safe-filename')
            );

        $fileName = $fileUploader->upload($file);

        $this->assertStringContainsString('safe-filename', $fileName);
        $this->assertStringEndsWith('.jpg', $fileName);
    }
}
