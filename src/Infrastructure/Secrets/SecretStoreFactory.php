<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure\Secrets;

use Throwable;
use AgentStudio\Contracts\SecretStore;

final class SecretStoreFactory
{
    public static function make(): SecretStore
    {
        return CoreSecretStore::available() ? new CoreSecretStore() : new EncryptedOptionSecretStore();
    }

    /**
     * Once WordPress has its own Secrets API, moves secrets saved before it existed.
     * Safe to call often: it does nothing when there is nothing to move.
     */
    public static function migrateToCore(): void
    {
        if (! CoreSecretStore::available()) {
            return;
        }

        $names = EncryptedOptionSecretStore::names();
        if ($names === []) {
            return;
        }

        $old = new EncryptedOptionSecretStore();
        $core = new CoreSecretStore();
        foreach ($names as $name) {
            $value = $old->get($name);
            try {
                if ($value !== null) {
                    $core->set($name, $value);
                }
                $old->delete($name);
            } catch (Throwable) {
                // Leave it where it is and try again next time.
            }
        }
    }
}
