<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type User } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    apiTokens?: Array<{
        id: number;
        name: string;
        is_active: boolean;
        last_used_at?: string | null;
        expires_at?: string | null;
        created_at: string;
    }>;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

const page = usePage();
const user = page.props.auth.user as User;

const form = useForm({
    name: user.name,
    email: user.email,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};

const newTokenForm = useForm({ name: 'Claude Hook', expires: '' });
const creating = ref(false);
const createdToken = ref<string | null>(null);

const createToken = async () => {
    creating.value = true;
    createdToken.value = null;
    try {
        const res = await window.fetch(route('settings.api-tokens.store'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: new URLSearchParams({ name: newTokenForm.name, expires: newTokenForm.expires }),
        });
        const data = await res.json();
        createdToken.value = data.token;
    } finally {
        creating.value = false;
    }
};

const downloadInstaller = () => {
    window.open(route('installers.codetrac'), '_blank');
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Profile information" description="Update your name and email address" />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" class="mt-1 block w-full" v-model="form.name" required autocomplete="name" placeholder="Full name" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Your email address is unverified.
                            <Link
                                :href="route('verification.send')"
                                method="post"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Click here to resend the verification email.
                            </Link>
                        </p>

                        <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600">
                            A new verification link has been sent to your email address.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">Save</Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="form.recentlySuccessful" class="text-sm text-neutral-600">Saved.</p>
                        </Transition>
                    </div>
                </form>
            </div>

            <div class="mt-10 space-y-4">
              <HeadingSmall title="API tokens" description="Generate and manage API tokens for Claude Code" />

              <div class="space-y-3">
                <div class="flex gap-2 items-end flex-wrap">
                  <div class="grid gap-2">
                    <Label for="token-name">Token name</Label>
                    <Input id="token-name" v-model="newTokenForm.name" placeholder="Claude Hook" />
                  </div>
                  <div class="grid gap-2">
                    <Label for="expires">Expires (e.g. 30 days)</Label>
                    <Input id="expires" v-model="newTokenForm.expires" placeholder="optional" />
                  </div>
                  <Button :disabled="creating" @click="createToken">{{ creating ? 'Creating…' : 'Create token' }}</Button>
                  <Button variant="secondary" @click="downloadInstaller">Download installer</Button>
                </div>

                <div v-if="createdToken" class="rounded-md border p-3 text-sm">
                  <div class="font-medium">Copy your token now:</div>
                  <div class="font-mono break-all">{{ createdToken }}</div>
                  <div class="text-muted-foreground">This token will not be shown again.</div>
                </div>

                <div class="space-y-2">
                  <div class="text-sm text-muted-foreground">Existing tokens</div>
                  <div class="rounded-md border divide-y">
                    <div v-for="t in props.apiTokens || []" :key="t.id" class="p-3 flex justify-between items-center">
                      <div>
                        <div class="font-medium">{{ t.name }}</div>
                        <div class="text-xs text-muted-foreground">Created {{ t.created_at }} • {{ t.is_active ? 'Active' : 'Inactive' }}</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
