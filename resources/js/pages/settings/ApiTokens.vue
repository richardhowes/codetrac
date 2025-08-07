<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import HeadingSmall from '@/components/HeadingSmall.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/settings/Layout.vue'

const state = reactive({
  name: 'Claude Hook',
  expires: '',
  creating: false,
  createdToken: '' as string | null,
  tokens: [] as Array<any>,
})

onMounted(async () => {
  try {
    const res = await fetch(route('profile.edit'))
    // no-op; tokens rendered on Profile previously; keeping page lightweight
  } catch {}
})

const createToken = async () => {
  state.creating = true
  state.createdToken = ''
  try {
    const res = await fetch(route('settings.api-tokens.store'), {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: new URLSearchParams({ name: state.name, expires: state.expires }),
    })
    const data = await res.json()
    state.createdToken = data.token
  } finally {
    state.creating = false
  }
}

const downloadInstaller = () => {
  window.open(route('installers.codetrac'), '_blank')
}
</script>

<template>
  <AppLayout :breadcrumbs="[{ title: 'API tokens', href: '/settings/api-tokens' }]">
    <Head title="API tokens" />
    <SettingsLayout>
      <div class="flex flex-col space-y-6">
        <HeadingSmall title="API tokens" description="Generate tokens and set up your Claude Code hook" />

        <div class="flex gap-2 items-end flex-wrap">
          <div class="grid gap-2">
            <Label for="token-name">Token name</Label>
            <Input id="token-name" v-model="state.name" placeholder="Claude Hook" />
          </div>
          <div class="grid gap-2">
            <Label for="expires">Expires (e.g. 30 days)</Label>
            <Input id="expires" v-model="state.expires" placeholder="optional" />
          </div>
          <Button :disabled="state.creating" @click="createToken">{{ state.creating ? 'Creating…' : 'Create token' }}</Button>
          <Button variant="secondary" @click="downloadInstaller">Download installer</Button>
        </div>

        <div v-if="state.createdToken" class="rounded-md border p-3 text-sm">
          <div class="font-medium">Copy your token now:</div>
          <div class="font-mono break-all">{{ state.createdToken }}</div>
          <div class="text-muted-foreground">This token will not be shown again.</div>
        </div>

        <div class="rounded-md border p-4 space-y-2 text-sm">
          <div class="font-medium">Hook setup</div>
          <ol class="list-decimal ml-4 space-y-1">
            <li>Run the installer to create <code>~/.codetrac/config</code>:<br /><code>bash -c "$(curl -fsSL {{ route('installers.codetrac') }})" TOKEN_HERE</code></li>
            <li>In Claude Code, open hooks via <code>/hooks</code>. Add a Stop hook pointing to your downloaded <code>codetrac.sh</code> or this repo’s script path.</li>
            <li>End a session to send data to your dashboard.</li>
          </ol>
        </div>
      </div>
    </SettingsLayout>
  </AppLayout>
  
</template>


