<?php

namespace Tests\Unit;

use Fastor\Validation\Mapper;
use Fastor\Validation\Attributes\Min;
use Fastor\Validation\Attributes\Max;
use Fastor\Validation\Attributes\Email;
use Fastor\Exceptions\ValidationException;

class TestDto
{
    #[Email]
    public string $email;
    
    #[Min(18)]
    public int $age;
}

class MapperTest
{
    public function testSuccessfulMapping()
    {
        $mapper = new Mapper();
        $data = ['email' => 'test@example.com', 'age' => 25];
        
        $obj = $mapper->map(TestDto::class, $data);
        
        if ($obj->email !== 'test@example.com' || $obj->age !== 25) {
            throw new \Exception("Mapping failed to set properties correctly");
        }
    }

    public function testValidationError()
    {
        $mapper = new Mapper();
        
        // Test invalid email
        try {
            $mapper->map(TestDto::class, ['email' => 'not-an-email', 'age' => 20]);
            throw new \Exception("Should have thrown ValidationException for email");
        } catch (ValidationException $e) {
            // Success
        }

        // Test invalid age (min)
        try {
            $mapper->map(TestDto::class, ['email' => 'test@example.com', 'age' => 10]);
            throw new \Exception("Should have thrown ValidationException for age");
        } catch (ValidationException $e) {
            // Success
        }
    }

    public function run()
    {
        echo "Running MapperTest... ";
        $this->testSuccessfulMapping();
        $this->testValidationError();
        echo "PASSED\n";
    }
}
