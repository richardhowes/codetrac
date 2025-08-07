<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    RadialLinearScale,
    Title,
    Tooltip,
} from 'chart.js';
import { Activity, Clock, Code2, DollarSign, FileCode2, FolderOpen, GitBranch, Terminal, TrendingUp, Users, Zap } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Bar, Doughnut, Line } from 'vue-chartjs';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, ArcElement, BarElement, RadialLinearScale, Filler);

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
        title: 'CodeTrac Analytics',
        href: '/dashboard',
    },
];

const selectedPeriod = ref(props.filters.period);
const selectedDeveloper = ref(props.filters.developer_id || null);
const selectedProject = ref(props.filters.project_id || null);
const isLoading = ref(false);

const periods = [
    { value: '24hours', label: '24h', icon: Clock },
    { value: '7days', label: '7d', icon: Activity },
    { value: '30days', label: '30d', icon: TrendingUp },
    { value: '90days', label: '90d', icon: GitBranch },
    { value: 'all', label: 'All', icon: Zap },
];

// Watch for filter changes and update the dashboard
watch([selectedPeriod, selectedDeveloper, selectedProject], ([newPeriod, newDeveloper, newProject]) => {
    isLoading.value = true;
    const params: any = { period: newPeriod };
    if (newDeveloper) params.developer_id = newDeveloper;
    if (newProject) params.project_id = newProject;

    router.get('/dashboard', params, {
        preserveState: false,
        preserveScroll: true,
        onFinish: () => {
            isLoading.value = false;
        },
    });
});

// Prepare developer and project options for selects
const developerOptions = computed(() => [
    { value: null, label: 'All Developers' },
    ...props.developers.map((dev) => ({
        value: dev.id,
        label: `${dev.username}@${dev.hostname}`,
    })),
]);

const projectOptions = computed(() => [
    { value: null, label: 'All Projects' },
    ...props.projects.map((proj) => ({
        value: proj.id,
        label: proj.name,
    })),
]);

// Calculate productivity metrics
const productivityScore = computed(() => {
    const linesPerHour = props.stats.summary.total_lines_written / Math.max(props.stats.summary.total_time_hours, 1);
    const efficiency = Math.min(100, (linesPerHour / 50) * 100); // Assume 50 lines/hour is 100% efficient
    return Math.round(efficiency);
});

const activityChartData = computed(() => ({
    labels: props.stats.activity_timeline.map((d) => d.date),
    datasets: [
        {
            label: 'Development Sessions',
            data: props.stats.activity_timeline.map((d) => d.sessions),
            borderColor: '#a855f7',
            backgroundColor: 'rgba(168, 85, 247, 0.2)',
            tension: 0.4,
            fill: true,
            yAxisID: 'y',
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5,
        },
        {
            label: 'Code Output',
            data: props.stats.activity_timeline.map((d) => d.lines_written),
            borderColor: '#06b6d4',
            backgroundColor: 'rgba(6, 182, 212, 0.2)',
            tension: 0.4,
            fill: true,
            yAxisID: 'y1',
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5,
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
            labels: {
                usePointStyle: true,
                padding: 20,
                font: {
                    size: 12,
                },
                color: 'rgb(156, 163, 175)', // text-gray-400
            },
        },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.9)',
            padding: 12,
            cornerRadius: 8,
            titleFont: {
                size: 14,
            },
            bodyFont: {
                size: 13,
            },
        },
    },
    scales: {
        x: {
            grid: {
                color: 'rgba(255, 255, 255, 0.06)',
            },
            ticks: {
                color: 'rgb(156, 163, 175)', // text-gray-400
            },
        },
        y: {
            type: 'linear' as const,
            display: true,
            position: 'left' as const,
            beginAtZero: true,
            grid: {
                color: 'rgba(255, 255, 255, 0.06)',
            },
            ticks: {
                color: 'rgb(156, 163, 175)', // text-gray-400
                callback: function (value: any) {
                    return Number.isInteger(value) ? value : null;
                },
                stepSize: 1,
            },
            title: {
                display: true,
                text: 'Sessions',
                color: 'rgb(156, 163, 175)',
            },
        },
        y1: {
            type: 'linear' as const,
            display: true,
            position: 'right' as const,
            beginAtZero: true,
            grid: {
                drawOnChartArea: false,
            },
            ticks: {
                color: 'rgb(156, 163, 175)', // text-gray-400
                callback: function (value: any) {
                    return Number.isInteger(value) ? value : null;
                },
            },
            title: {
                display: true,
                text: 'Lines of Code',
                color: 'rgb(156, 163, 175)',
            },
        },
    },
};

// Polar area chart for file types
const fileTypesData = computed(() => {
    const data = props.stats.file_types_written.slice(0, 6);
    return {
        labels: data.map((f) => f.type || 'Unknown'),
        datasets: [
            {
                label: 'Files Written',
                data: data.map((f) => f.count || 0),
                backgroundColor: [
                    'rgba(168, 85, 247, 0.8)',
                    'rgba(6, 182, 212, 0.8)',
                    'rgba(34, 211, 238, 0.8)',
                    'rgba(251, 191, 36, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                ],
                borderWidth: 2,
                borderColor: 'rgba(0, 0, 0, 0.1)',
            },
        ],
    };
});

// Horizontal bar for commands
const topCommandsData = computed(() => {
    const commands = props.stats.top_commands.slice(0, 8);
    return {
        labels: commands.map((c) => c.command || 'Unknown'),
        datasets: [
            {
                label: 'Executions',
                data: commands.map((c) => c.count || 0),
                backgroundColor: commands.map((_, i) => {
                    const opacity = 0.9 - i * 0.1;
                    return `rgba(168, 85, 247, ${opacity})`;
                }),
                borderColor: 'rgba(168, 85, 247, 1)',
                borderWidth: 1,
                borderRadius: 4,
            },
        ],
    };
});

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom' as const,
            labels: {
                padding: 15,
                usePointStyle: true,
                font: {
                    size: 11,
                },
                color: 'rgb(156, 163, 175)', // text-gray-400
            },
        },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.9)',
            padding: 10,
            cornerRadius: 6,
        },
    },
    cutout: '65%',
};

const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y' as const,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.9)',
            padding: 10,
            cornerRadius: 6,
        },
    },
    scales: {
        x: {
            grid: {
                color: 'rgba(255, 255, 255, 0.06)',
            },
            ticks: {
                color: 'rgb(156, 163, 175)', // text-gray-400
                callback: function (value: any) {
                    return Number.isInteger(value) ? value : null;
                },
            },
        },
        y: {
            grid: {
                display: false,
            },
            ticks: {
                color: 'rgb(156, 163, 175)', // text-gray-400
                font: {
                    size: 11,
                },
            },
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

function getProductivityLabel(score: number): string {
    if (score >= 80) return 'Excellent';
    if (score >= 60) return 'Good';
    if (score >= 40) return 'Average';
    return 'Needs Improvement';
}

function getProductivityColor(score: number): string {
    if (score >= 80) return 'text-green-600';
    if (score >= 60) return 'text-blue-600';
    if (score >= 40) return 'text-yellow-600';
    return 'text-red-600';
}

function formatCost(cost: string | number): string {
    // Handle null/undefined
    if (cost === null || cost === undefined) {
        return '$0.00';
    }

    // If it's a string, remove any dollar sign and parse
    if (typeof cost === 'string') {
        const cleanedCost = cost.replace(/^\$/, '');
        const numCost = parseFloat(cleanedCost);
        return isNaN(numCost) ? '$0.00' : `$${numCost.toFixed(2)}`;
    }

    // If it's a number, format it
    return `$${cost.toFixed(2)}`;
}

function formatDuration(duration: string): string {
    // Handle various duration formats
    if (!duration || duration === '0' || duration === '0 min') {
        return '< 1 min';
    }
    return duration;
}

function formatTask(task: string | null): string {
    if (!task || task === 'No description') {
        return 'Development session';
    }
    return task;
}

function formatDate(date: string): string {
    const d = new Date(date);
    const now = new Date();
    const diffMs = now.getTime() - d.getTime();
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));

    if (diffHours < 1) {
        const diffMins = Math.floor(diffMs / (1000 * 60));
        return `${diffMins} min ago`;
    } else if (diffHours < 24) {
        return `${diffHours}h ago`;
    } else if (diffHours < 48) {
        return 'Yesterday';
    } else {
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
}
</script>

<template>
    <Head title="CodeTrac Analytics" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header with Period Selector -->
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="bg-gradient-to-r from-purple-600 to-cyan-500 bg-clip-text text-2xl font-bold text-transparent sm:text-3xl">
                            Development Analytics
                        </h1>
                        <p class="mt-1 text-sm text-muted-foreground sm:text-base">Track your Claude Code productivity and costs</p>
                    </div>
                    <div class="flex gap-1 overflow-x-auto rounded-lg bg-secondary/50 p-1">
                        <Button
                            v-for="period in periods"
                            :key="period.value"
                            :variant="selectedPeriod === period.value ? 'default' : 'ghost'"
                            size="sm"
                            @click="selectedPeriod = period.value"
                            class="min-w-fit gap-1 sm:gap-2"
                            :disabled="isLoading"
                        >
                            <component :is="period.icon" class="h-3 w-3" />
                            <span class="text-xs sm:text-sm">{{ period.label }}</span>
                        </Button>
                    </div>
                </div>

                <!-- Filter Dropdowns -->
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="flex flex-1 items-center gap-2 sm:max-w-xs">
                        <Users class="h-4 w-4 text-muted-foreground" />
                        <Select
                            v-model="selectedDeveloper"
                            :options="developerOptions"
                            placeholder="All Developers"
                            class="flex-1"
                            :disabled="isLoading"
                        />
                    </div>
                    <div class="flex flex-1 items-center gap-2 sm:max-w-xs">
                        <FolderOpen class="h-4 w-4 text-muted-foreground" />
                        <Select v-model="selectedProject" :options="projectOptions" placeholder="All Projects" class="flex-1" :disabled="isLoading" />
                    </div>
                </div>
            </div>

            <!-- Loading Overlay -->
            <div v-if="isLoading" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                <div class="flex items-center gap-3 rounded-lg bg-card p-4 shadow-lg">
                    <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-purple-500"></div>
                    <span class="text-sm font-medium">Loading analytics...</span>
                </div>
            </div>

            <!-- Key Metrics Cards with Icons -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <Card class="border-l-4 border-l-purple-500 transition-all hover:scale-[1.02] hover:shadow-lg">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-xs font-medium sm:text-sm">Total Investment</CardTitle>
                            <DollarSign class="h-4 w-4 text-purple-500" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-xl font-bold sm:text-2xl">{{ formatCost(stats.summary.total_cost) }}</div>
                        <p class="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                            <Zap class="h-3 w-3" />
                            {{ formatNumber(stats.summary.total_tokens) }} tokens
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-l-4 border-l-cyan-500 transition-all hover:scale-[1.02] hover:shadow-lg">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-xs font-medium sm:text-sm">Code Generated</CardTitle>
                            <Code2 class="h-4 w-4 text-cyan-500" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-xl font-bold sm:text-2xl">{{ formatNumber(stats.summary.total_lines_written) }}</div>
                        <p class="text-xs text-muted-foreground">Lines of code</p>
                    </CardContent>
                </Card>

                <Card class="border-l-4 border-l-green-500 transition-all hover:scale-[1.02] hover:shadow-lg">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-xs font-medium sm:text-sm">Time Invested</CardTitle>
                            <Clock class="h-4 w-4 text-green-500" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-xl font-bold sm:text-2xl">{{ stats.summary.total_time_hours }}h</div>
                        <p class="text-xs text-muted-foreground">Development hours</p>
                    </CardContent>
                </Card>

                <Card class="border-l-4 border-l-yellow-500 transition-all hover:scale-[1.02] hover:shadow-lg">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-xs font-medium sm:text-sm">Sessions</CardTitle>
                            <Activity class="h-4 w-4 text-yellow-500" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-xl font-bold sm:text-2xl">{{ stats.summary.total_sessions }}</div>
                        <p class="text-xs text-muted-foreground">~{{ stats.summary.avg_session_minutes }} min avg</p>
                    </CardContent>
                </Card>

                <Card class="border-l-4 border-l-pink-500 transition-all hover:scale-[1.02] hover:shadow-lg sm:col-span-2 lg:col-span-1">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-xs font-medium sm:text-sm">Productivity</CardTitle>
                            <TrendingUp class="h-4 w-4 text-pink-500" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-baseline gap-2">
                            <div class="text-xl font-bold sm:text-2xl">{{ productivityScore }}%</div>
                            <span :class="getProductivityColor(productivityScore)" class="text-xs font-medium">
                                {{ getProductivityLabel(productivityScore) }}
                            </span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-secondary">
                            <div
                                class="h-full bg-gradient-to-r from-purple-500 to-pink-500 transition-all duration-500"
                                :style="`width: ${productivityScore}%`"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Main Activity Chart -->
            <Card class="col-span-full">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Development Activity</CardTitle>
                            <CardDescription>Sessions and code output over time</CardDescription>
                        </div>
                        <div class="flex gap-4 text-sm">
                            <div class="flex items-center gap-2">
                                <div class="h-3 w-3 rounded-full bg-purple-500"></div>
                                <span class="text-muted-foreground">Sessions</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="h-3 w-3 rounded-full bg-cyan-500"></div>
                                <span class="text-muted-foreground">Lines</span>
                            </div>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="h-64 sm:h-80">
                        <Line :data="activityChartData" :options="activityChartOptions" />
                    </div>
                </CardContent>
            </Card>

            <!-- Analytics Grid -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- File Types Chart -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <FileCode2 class="h-4 w-4 text-purple-500" />
                            <CardTitle>Code Distribution</CardTitle>
                        </div>
                        <CardDescription>Files written by type</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="h-64">
                            <Doughnut :data="fileTypesData" :options="doughnutOptions" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Commands Chart -->
                <Card class="col-span-1 lg:col-span-2">
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <Terminal class="h-4 w-4 text-cyan-500" />
                            <CardTitle>Command Usage</CardTitle>
                        </div>
                        <CardDescription>Most frequently executed commands</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="h-64">
                            <Bar :data="topCommandsData" :options="barOptions" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Recent Sessions with enhanced styling -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Recent Sessions</CardTitle>
                            <CardDescription>Latest development activities</CardDescription>
                        </div>
                        <Button variant="outline" size="sm" class="gap-2">
                            <GitBranch class="h-3 w-3" />
                            View All
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border/50">
                                    <th class="py-3 text-left font-medium text-muted-foreground">Task</th>
                                    <th class="py-3 text-left font-medium text-muted-foreground">Developer</th>
                                    <th class="py-3 text-left font-medium text-muted-foreground">Project</th>
                                    <th class="py-3 text-left font-medium text-muted-foreground">When</th>
                                    <th class="py-3 text-right font-medium text-muted-foreground">Duration</th>
                                    <th class="py-3 text-right font-medium text-muted-foreground">Output</th>
                                    <th class="py-3 text-right font-medium text-muted-foreground">Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="session in stats.recent_sessions"
                                    :key="session.id"
                                    class="border-b border-border/30 transition-colors hover:bg-secondary/30"
                                >
                                    <td class="max-w-xs py-3">
                                        <div class="truncate font-medium">{{ formatTask(session.task) }}</div>
                                    </td>
                                    <td class="py-3">
                                        <span class="text-muted-foreground">{{ session.developer }}</span>
                                    </td>
                                    <td class="py-3">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-secondary px-2 py-1 text-xs">
                                            <GitBranch class="h-3 w-3" />
                                            {{ session.project }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-muted-foreground">{{ formatDate(session.started_at) }}</td>
                                    <td class="py-3 text-right">
                                        <span class="inline-flex items-center gap-1">
                                            <Clock class="h-3 w-3 text-muted-foreground" />
                                            {{ formatDuration(session.duration) }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-right">
                                        <span
                                            class="font-mono text-xs font-semibold"
                                            :class="
                                                session.lines_written > 0
                                                    ? 'bg-gradient-to-r from-purple-600 to-cyan-600 bg-clip-text text-transparent'
                                                    : 'text-muted-foreground'
                                            "
                                        >
                                            {{ session.lines_written || 0 }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-right font-medium">{{ formatCost(session.cost) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Top Files with enhanced visualization -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Most Active Files</CardTitle>
                            <CardDescription>Files with highest development activity</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div
                            v-for="(file, index) in stats.top_files.slice(0, 5)"
                            :key="file.path"
                            class="flex items-center gap-4 rounded-lg p-3 transition-colors hover:bg-secondary/50"
                        >
                            <div
                                class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-cyan-500 font-bold text-white"
                            >
                                {{ index + 1 }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-mono text-sm font-medium">{{ file.name || 'Unknown' }}</div>
                                <div class="truncate font-mono text-xs text-muted-foreground">{{ file.path || '/' }}</div>
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <div class="text-sm font-semibold">{{ formatNumber(file.lines || 0) }} lines</div>
                                <div class="text-xs text-muted-foreground">{{ file.edits || 0 }} edits</div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
