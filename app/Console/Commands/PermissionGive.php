<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class PermissionGive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:give {permission} {codpes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Atribui permission a um usuário';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $guard_name = 'web';
        $p = Permission::where([
            'name' => $this->argument('permission'),
            'guard_name' => $guard_name
        ])->firstOrFail();

        $u = User::where('codpes', $this->argument('codpes'))->firstOrFail();

        $u->givePermissionTo($p);

        return 0;
    }
}
