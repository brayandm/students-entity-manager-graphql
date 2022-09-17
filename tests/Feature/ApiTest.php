<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use App\User;
use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ApiTest extends TestCase
{
    use DatabaseMigrations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function register()
    {
        $response = $this->post(
            '/api/register',
            [
                'name' => 'BrayanD',
                'email' => 'brayanduranmedina@gmail.com',
                'password' => '12345678'
            ]
        );

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'user' => ['name', 'email', 'id'],
            'access_token',
            'token_type',
        ]);

        $response->assertJsonPath('user.id', 1);
        $response->assertJsonPath('user.name', 'BrayanD');
        $response->assertJsonPath('user.email', 'brayanduranmedina@gmail.com');
        $response->assertJsonPath('token_type', 'Bearer');

        $this->app->get('auth')->forgetGuards();

        return $response;
    }

    public function test_register_and_get_students()
    {
        //Filling database

        DB::table('students')->insert(['firstname' => 'Brayan']);
        DB::table('students')->insert(['firstname' => 'Miranda']);
        DB::table('students')->insert(['firstname' => 'Carlos']);

        //Register

        $response = $this->register();

        //Get token

        $token = $response['access_token'];

        //Getting students

        $response = $this->graphQL(/** @lang GraphQL */ '
            {
                getStudents {
                    firstname
                }
            }
            ', [], [], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(200);

        $response->assertJsonPath('data.getStudents.0.firstname', 'Brayan');
        $response->assertJsonPath('data.getStudents.1.firstname', 'Miranda');
        $response->assertJsonPath('data.getStudents.2.firstname', 'Carlos');

        //Logout

        $response = $this->post('/api/logout', [], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(200);

        $response->assertJsonPath('message', 'Successful logout');

        $this->app->get('auth')->forgetGuards();

        //Getting students and fails

        $response = $this->graphQL(/** @lang GraphQL */ '
            {
                getStudents {
                    firstname
                }
            }
            ', [], [], ['Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json']);

        $response->assertStatus(200);

        $response->assertJsonPath('errors.0.message', 'Unauthenticated.');
    }
}