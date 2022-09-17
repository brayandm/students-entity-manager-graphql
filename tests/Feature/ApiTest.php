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

        $response->assertJsonStructure([
            'errors'
        ]);

        $response->assertJsonPath('errors.0.message', 'Unauthenticated.');
    }

    public function test_get_student_by_id()
    {
        //Filling database

        Student::factory(10)->create();

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

        $response->assertJsonStructure([
            'data' => ['getStudents']
        ]);

        $this->assertEquals(10, count($response['data']['getStudents']));

        //Getting students by id

        $response = $this->graphQL(/** @lang GraphQL */ '
            {
                getStudent(id: 1) {
                    id
                    firstname
                    lastname
                    email
                    birthdate
                    address
                    score
                    created_at
                    updated_at
                }
            }
            ', [], [], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'data' => [
                'getStudent' =>
                    ['id',
                    'firstname',
                    'lastname',
                    'email',
                    'birthdate',
                    'address',
                    'score',
                    'created_at',
                    'updated_at']]
        ]);
    }

    public function test_delete_students()
    {
        //Filling database

        Student::factory(10)->create();

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

        $response->assertJsonStructure([
            'data' => ['getStudents']
        ]);

        $this->assertEquals(10, count($response['data']['getStudents']));

        //Deleting students

        for ($i = 1; $i <=10; $i++)
        {
            $response = $this->graphQL(/** @lang GraphQL */ '
                mutation ($id: ID!){
                    deleteStudent(id: $id) {
                        id
                    }
                }
                ', ['id' => $i], [], ['Authorization' => 'Bearer ' . $token]);

            $response->assertStatus(200);

            $response->assertJsonStructure([
                'data' => ['deleteStudent']
            ]);
        }

        //Counting students

        $response = $this->graphQL(/** @lang GraphQL */ '
            {
                getStudents {
                    firstname
                }
            }
            ', [], [], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'data' => ['getStudents']
        ]);

        $this->assertEquals(0, count($response['data']['getStudents']));
    }

    public function test_add_student()
    {
        //Register

        $response = $this->register();

        //Get token

        $token = $response['access_token'];

        //Adding student

        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation {
                addStudent(firstname: "Brayan", lastname: "Duran",
                email: "brayanduranmedina@gmail.com", birthdate: "2001-9-22",
                address: "Carrer del Clot", score: 10) {
                    id
                }
            }
            ', [], [], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'data' => ['addStudent']
        ]);

        //Counting students

        $response = $this->graphQL(/** @lang GraphQL */ '
            {
                getStudents {
                    firstname
                }
            }
            ', [], [], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'data' => ['getStudents']
        ]);

        $this->assertEquals(1, count($response['data']['getStudents']));
    }

    public function test_login_and_logout()
    {
        //Register format incorrect

        $response = $this->post(
            '/api/register',
            [
                'name' => 'BrayanD',
                'email' => 'brayanduranmedina',
                'password' => '12345678'
            ]
        );

        $response->assertStatus(400);

        //Register

        $response = $this->register();

        //Login format incorrect

        $response = $this->post('/api/login', [
            'email' => 'brayanduranmedina',
            'password' => '12345678'
        ]);

        $response->assertStatus(400);

        $this->app->get('auth')->forgetGuards();

        //Login unauthenticated

        $response = $this->post('/api/login', [
            'email' => 'brayanduranmedina@gmail.com',
            'password' => '123456789'
        ]);

        $response->assertStatus(401);

        $this->app->get('auth')->forgetGuards();

        //Login

        $response = $this->post('/api/login', [
            'email' => 'brayanduranmedina@gmail.com',
            'password' => '12345678'
        ]);

        $response->assertStatus(200);

        $this->app->get('auth')->forgetGuards();

        //Get token

        $token = $response['access_token'];

        //Logout

        $response = $this->post('/api/logoutall', [], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(200);

        $response->assertJsonPath('message', 'Successful logout');

        $this->app->get('auth')->forgetGuards();
    }

    public function test_edit()
    {
        //Register

        $response = $this->register();

        //Get token

        $token = $response['access_token'];

        //Adding student

        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation {
                addStudent(firstname: "Brayan", lastname: "Duran",
                email: "brayanduranmedina@gmail.com", birthdate: "2001-9-22",
                address: "Carrer del Clot", score: 10) {
                    firstname
                }
            }
            ', [], [], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'data' => ['addStudent']
        ]);

        $response->assertJsonPath('data.addStudent.firstname', 'Brayan');

        //Editing student fails incorrect email

        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation {
                addStudent(firstname: "Brayan", lastname: "Duran",
                email: "brayanduranmedina", birthdate: "2001-9-22",
                address: "Carrer del Clot", score: 10) {
                    firstname
                }
            }
            ', [], [], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'errors'
        ]);

        //Editing student

        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation {
                updateStudent(id: 1, firstname: "Daniel", lastname: "Duran",
                email: "brayanduranmedina@gmail.com", birthdate: "2001-9-22",
                address: "Carrer del Clot", score: 10) {
                    firstname
                }
            }
            ', [], [], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'data' => ['updateStudent']
        ]);

        $response->assertJsonPath('data.updateStudent.firstname', 'Daniel');
    }
}
