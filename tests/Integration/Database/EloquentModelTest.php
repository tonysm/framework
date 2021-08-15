<?php

namespace Illuminate\Tests\Integration\Database;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * @group integration
 */
class EloquentModelTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_model1', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamp('nullable_date')->nullable();
        });

        Schema::create('test_model2', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('title');
        });

        Schema::create('test_model3', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });
    }

    public function testUserCanUpdateNullableDate()
    {
        $user = TestModel1::create([
            'nullable_date' => null,
        ]);

        $user->fill([
            'nullable_date' => $now = Carbon::now(),
        ]);
        $this->assertTrue($user->isDirty('nullable_date'));

        $user->save();
        $this->assertEquals($now->toDateString(), $user->nullable_date->toDateString());
    }

    public function testAttributeChanges()
    {
        $user = TestModel2::create([
            'name' => Str::random(), 'title' => Str::random(),
        ]);

        $this->assertEmpty($user->getDirty());
        $this->assertEmpty($user->getChanges());
        $this->assertFalse($user->isDirty());
        $this->assertFalse($user->wasChanged());

        $user->name = $name = Str::random();

        $this->assertEquals(['name' => $name], $user->getDirty());
        $this->assertEmpty($user->getChanges());
        $this->assertTrue($user->isDirty());
        $this->assertFalse($user->wasChanged());

        $user->save();

        $this->assertEmpty($user->getDirty());
        $this->assertEquals(['name' => $name], $user->getChanges());
        $this->assertTrue($user->wasChanged());
        $this->assertTrue($user->wasChanged('name'));
    }

    public function testCustomAccessorUsingResolver()
    {
        $number = 1;

        TestModel3::resolveGetMutatorUsing('name_incremented', function ($model) use (&$number) {
            return $model->name . '.' . $number++;
        });

        $model = TestModel3::create([
            'name' => 'lorem',
        ]);

        $this->assertEquals('lorem.1', $model->name_incremented);
        $this->assertEquals('lorem.2', $model->name_incremented);
    }

    public function testCustomMutatorUsingResolver()
    {
        $number = 1;

        TestModel3::resolveSetMutatorUsing('name_incrementing', function ($model, $value) use (&$number) {
            $model->name = $value . '.' . $number++;
        });

        $model = TestModel3::create([
            'name' => 'old val',
        ]);

        $model->update(['name_incrementing' => 'ipsum']);
        $this->assertEquals('ipsum.1', $model->name);

        $model->update(['name_incrementing' => 'lorem']);
        $this->assertEquals('lorem.2', $model->name);
    }

    public function testSetMutatorDoesntMakeTheModelDirty()
    {
        $called = false;

        TestModel3::resolveSetMutatorUsing('name_incrementing', function ($model, $value) use (&$called) {
            // Does nothing, so the model state does not change.
            $called = true;
        });

        $model = TestModel3::create([
            'name' => 'old val',
        ])->fresh();

        $this->assertFalse($called);

        $model->fill(['name_incrementing' => 'something else']);

        $this->assertTrue($called);
        $this->assertFalse($model->isDirty());
        $this->assertEquals('old val', $model->name);
    }
}

class TestModel1 extends Model
{
    public $table = 'test_model1';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['nullable_date' => 'datetime'];
}

class TestModel2 extends Model
{
    public $table = 'test_model2';
    public $timestamps = false;
    protected $guarded = [];
}

class TestModel3 extends Model
{
    public $table = 'test_model3';
    public $timestamps = false;
    protected $guarded = [];
}
