<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Line, Doughnut, Bar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    ArcElement,
    BarElement,
} from 'chart.js';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    ArcElement,
    BarElement
);

interface Props {
    stats: {
        summary: {
            total_cost: number;
            total_lines_written: number;
            total_time_hours: number;
            total_sessions: number;
            total_tokens: number;
            avg_session_minutes: number;
        };
        activity_timeline: Array<{
            date: string;
            sessions: number;
            lines_written: number;
        }>;
        file_types_written: Array<{
            type: string;
            count: number;
            percentage: number;
        }>;
        file_types_read: Array<{
            type: string;
            count: number;
            percentage: number;
        }>;
        top_commands: Array<{
            command: string;
            count: number;
        }>;
        recent_sessions: Array<{
            id: number;
            session_id: string;
            task: string;
            developer: string;
            project: string;
            started_at: string;
            duration: string;
            lines_written: number;
            cost: string;
            status: string;
        }>;
        top_files: Array<{
            name: string;
            path: string;
            lines: number;
            edits: number;
        }>;
    };
    developers: Array<any>;
    projects: Array<any>;
    filters: {
        period: string;
        developer_id?: number;
        project_id?: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'DevTrack Dashboard',
        href: '/dashboard',
    },
];

const selectedPeriod = ref(props.filters.period);

const periods = [
    { value: '24hours', label: 'Last 24 Hours' },
    { value: '7days', label: 'Last 7 Days' },
    { value: '30days', label: 'Last 30 Days' },
    { value: '90days', label: 'Last 90 Days' },
    { value: 'all', label: 'All Time' },
];

const activityChartData = computed(() => ({
    labels: props.stats.activity_timeline.map(d => d.date),
    datasets: [
        {
            label: 'Sessions',
            data: props.stats.activity_timeline.map(d => d.sessions),
            borderColor: 'rgb(99, 102, 241)',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            yAxisID: 'y',
        },
        {
            label: 'Lines Written',
            data: props.stats.activity_timeline.map(d => d.lines_written),
            borderColor: 'rgb(34, 197, 94)',
            backgroundColor: 'rgba(34, 197, 94, 0.1)',
            yAxisID: 'y1',
        },
    ],
}));

const activityChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index' as const,
        intersect: false,
    },
    plugins: {
        legend: {
            position: 'top' as const,
        },
    },
    scales: {
        y: {
            type: 'linear' as const,
            display: true,
            position: 'left' as const,
        },
        y1: {
            type: 'linear' as const,
            display: true,
            position: 'right' as const,
            grid: {
                drawOnChartArea: false,
            },
        },
    },
};

const fileTypesWrittenData = computed(() => ({
    labels: props.stats.file_types_written.map(f => f.type),
    datasets: [{
        data: props.stats.file_types_written.map(f => f.count),
        backgroundColor: [
            '#3b82f6',
            '#ef4444',
            '#10b981',
            '#f59e0b',
            '#8b5cf6',
            '#ec4899',
            '#06b6d4',
            '#84cc16',
            '#6b7280',
        ],
    }],
}));

const fileTypesReadData = computed(() => ({
    labels: props.stats.file_types_read.map(f => f.type),
    datasets: [{
        data: props.stats.file_types_read.map(f => f.count),
        backgroundColor: [
            '#3b82f6',
            '#ef4444',
            '#10b981',
            '#f59e0b',
            '#8b5cf6',
            '#ec4899',
            '#06b6d4',
            '#84cc16',
            '#6b7280',
        ],
    }],
}));

const topCommandsData = computed(() => ({
    labels: props.stats.top_commands.map(c => c.command),
    datasets: [{
        label: 'Usage Count',
        data: props.stats.top_commands.map(c => c.count),
        backgroundColor: 'rgba(99, 102, 241, 0.5)',
    }],
}));

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'right' as const,
        },
    },
};

const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y' as const,
    plugins: {
        legend: {
            display: false,
        },
    },
};

function formatNumber(num: number): string {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
}
</script>

<template>
    <Head title="DevTrack Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Period Selector -->
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold">Claude Code Analytics</h1>
                <div class="flex gap-2">
                    <Button
                        v-for="period in periods"
                        :key="period.value"
                        :variant="selectedPeriod === period.value ? 'default' : 'outline'"
                        size="sm"
                        @click="selectedPeriod = period.value"
                    >
                        {{ period.label }}
                    </Button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium">Project Cost</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">${{ stats.summary.total_cost.toFixed(2) }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ formatNumber(stats.summary.total_tokens) }} tokens used
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium">Lines Written</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatNumber(stats.summary.total_lines_written) }}</div>
                        <p class="text-xs text-muted-foreground">
                            Total lines of code written
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium">Time Spent</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.summary.total_time_hours }}h</div>
                        <p class="text-xs text-muted-foreground">
                            Total hours on this project
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium">Sessions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.summary.total_sessions }}</div>
                        <p class="text-xs text-muted-foreground">
                            Avg {{ stats.summary.avg_session_minutes }} min/session
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Activity Timeline -->
            <Card>
                <CardHeader>
                    <CardTitle>Activity Overview</CardTitle>
                    <CardDescription>Daily sessions and lines written</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="h-64">
                        <Line :data="activityChartData" :options="activityChartOptions" />
                    </div>
                </CardContent>
            </Card>

            <!-- File Types and Commands -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Top File Types Written</CardTitle>
                        <CardDescription>Most frequently edited file types</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="h-64">
                            <Doughnut :data="fileTypesWrittenData" :options="doughnutOptions" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Top File Types Read</CardTitle>
                        <CardDescription>Most frequently viewed file types</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="h-64">
                            <Doughnut :data="fileTypesReadData" :options="doughnutOptions" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Top Bash Commands</CardTitle>
                        <CardDescription>Most frequently used commands</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="h-64">
                            <Bar :data="topCommandsData" :options="barOptions" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Recent Sessions Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Latest Sessions</CardTitle>
                    <CardDescription>Recent Claude Code sessions</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-2">Task</th>
                                    <th class="text-left py-2">Developer</th>
                                    <th class="text-left py-2">Project</th>
                                    <th class="text-left py-2">When</th>
                                    <th class="text-right py-2">Duration</th>
                                    <th class="text-right py-2">Lines</th>
                                    <th class="text-right py-2">Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="session in stats.recent_sessions" :key="session.id" class="border-b">
                                    <td class="py-2 max-w-xs truncate">{{ session.task }}</td>
                                    <td class="py-2">{{ session.developer }}</td>
                                    <td class="py-2">{{ session.project }}</td>
                                    <td class="py-2">{{ session.started_at }}</td>
                                    <td class="py-2 text-right">{{ session.duration }}</td>
                                    <td class="py-2 text-right">{{ session.lines_written }}</td>
                                    <td class="py-2 text-right">{{ session.cost }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Top Files -->
            <Card>
                <CardHeader>
                    <CardTitle>Top Files</CardTitle>
                    <CardDescription>Files with the most lines written</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-2">File</th>
                                    <th class="text-left py-2">Path</th>
                                    <th class="text-right py-2">Lines Written</th>
                                    <th class="text-right py-2">Edits</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="file in stats.top_files" :key="file.path" class="border-b">
                                    <td class="py-2 font-mono text-xs">{{ file.name }}</td>
                                    <td class="py-2 font-mono text-xs text-muted-foreground max-w-md truncate">{{ file.path }}</td>
                                    <td class="py-2 text-right">{{ file.lines }}</td>
                                    <td class="py-2 text-right">{{ file.edits }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>