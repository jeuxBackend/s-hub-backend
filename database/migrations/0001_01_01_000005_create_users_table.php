<?php
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Auth & Identification
            $table->string('email')->unique();
            $table->string('phone_number')->unique();
            $table->foreignId('institution_id')->nullable()->constrained('institutions')->onDelete(null)->index();
            $table->string('password');
            $table->enum('role', UserRole::values())
                ->index()
                ->default(UserRole::Parent->value);
            $table->boolean('is_school_admin')->default(false);

            // OTP & Device Info
            $table->string('otp_code')->nullable();
            $table->boolean('otp_verified')->default(false);
            $table->string('device_id')->nullable();
            $table->string('fcm_token')->nullable();
            $table->json('permissions')->nullable();

            // Personal Info
            $table->string('first_name')->nullable();
            $table->string('sur_name')->nullable();
            $table->string('title')->nullable();
            $table->string('position')->nullable();
            $table->string('country')->nullable();
            $table->string('profile_picture')->nullable();
            $table->string('staff_number')->nullable()->index(); // safer than unique

            // Security
            $table->text('security_question')->nullable();
            $table->text('answer_security_question')->nullable();

            // Guardian Info
            $table->enum('guardian_type', ['father', 'mother', 'guardian'])->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_relation')->nullable();
            $table->string('guardian_phone_number')->nullable();
            $table->string('alternative_guardian_phone_number')->nullable();

            // Alternative contacts
            $table->string('alternative_email')->nullable();
            $table->string('alternative_phone_number')->nullable();

            // User Relations
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null')
                ->index()
                ->name('users_created_by_foreign');

            $table->boolean('status')->default(true);
            $table->boolean('notifications_enabled')->default(true);
            $table->softDeletes();
            // Extras
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};


