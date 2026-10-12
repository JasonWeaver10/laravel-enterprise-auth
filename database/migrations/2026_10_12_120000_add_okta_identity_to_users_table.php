<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Okta's "sub" claim, which is the only stable handle on a person.
            // Email is not: people change names, and a changed email would
            // otherwise orphan the account it belongs to.
            $table->string('okta_id')->nullable()->unique()->after('email');

            $table->timestamp('last_sso_at')->nullable()->after('email_verified_at');
        });

        // A user who only ever signs in through Okta never sets a password.
        // Leaving the column required would mean writing a meaningless
        // placeholder hash onto every federated account, which looks like a
        // credential to anyone reading the table later.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['okta_id']);
            $table->dropColumn(['okta_id', 'last_sso_at']);
        });
    }
};
