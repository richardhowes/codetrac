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
    RadialLinearScale,
    Filler,
} from 'chart.js';
import { 
    TrendingUp, 
    Clock, 
    Code2, 
    DollarSign, 
    Activity,
    Zap,
    FileCode2,
    Terminal,
    GitBranch,
} from 'lucide-vue-next';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    ArcElement,
    BarElement,
    RadialLinearScale,
    Filler
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
        title: 'DevTrack Analytics',
        href: '/dashboard',
    },
];

const selectedPeriod = ref(props.filters.period);

const periods = [
    { value: '24hours', label: '24h', icon: Clock },
    { value: '7days', label: '7d', icon: Activity },
    { value: '30days', label: '30d', icon: TrendingUp },
    { value: '90days', label: '90d', icon: GitBranch },
    { value: 'all', label: 'All', icon: Zap },
];

// Calculate productivity metrics
const productivityScore = computed(() => {
    const linesPerHour = props.stats.summary.total_lines_written / Math.max(props.stats.summary.total_time_hours, 1);
    const efficiency = Math.min(100, (linesPerHour / 50) * 100); // Assume 50 lines/hour is 100% efficient
    return Math.round(efficiency);
});

const activityChartData = computed(() => ({
    labels: props.stats.activity_timeline.map(d => d.date),
    datasets: [
        {
            label: 'Development Sessions',
            data: props.stats.activity_timeline.map(d => d.sessions),
            borderColor: '#a855f7',
            backgroundColor: 'rgba(168, 85, 247, 0.1)',
            tension: 0.4,
            fill: true,
            yAxisID: 'y',
        },
        {
            label: 'Code Output',
            data: props.stats.activity_timeline.map(d => d.lines_written),
            borderColor: '#06b6d4',
            backgroundColor: 'rgba(6, 182, 212, 0.1)',
            tension: 0.4,
            fill: true,
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
            labels: {
                usePointStyle: true,
                padding: 20,
                font: {
                    size: 12,
                },
            },
        },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
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
                display: false,
            },
        },
        y: {
            type: 'linear' as const,
            display: true,
            position: 'left' as const,
            grid: {
                color: 'rgba(0, 0, 0, 0.05)',
            },
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

// Polar area chart for file types
const fileTypesData = computed(() => ({
    labels: props.stats.file_types_written.slice(0, 6).map(f => f.type),
    datasets: [{
        label: 'Files Written',
        data: props.stats.file_types_written.slice(0, 6).map(f => f.count),
        backgroundColor: [
            'rgba(168, 85, 247, 0.7)',
            'rgba(6, 182, 212, 0.7)',
            'rgba(34, 211, 238, 0.7)',
            'rgba(251, 191, 36, 0.7)',
            'rgba(239, 68, 68, 0.7)',
            'rgba(16, 185, 129, 0.7)',
        ],
        borderWidth: 2,
        borderColor: '#fff',
    }],
}));

// Horizontal bar for commands
const topCommandsData = computed(() => ({
    labels: props.stats.top_commands.slice(0, 8).map(c => c.command),
    datasets: [{
        label: 'Executions',
        data: props.stats.top_commands.slice(0, 8).map(c => c.count),
        backgroundColor: 'rgba(168, 85, 247, 0.6)',
        borderColor: 'rgba(168, 85, 247, 1)',
        borderWidth: 1,
        borderRadius: 4,
    }],
}));

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
            },
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
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 10,
            cornerRadius: 6,
        },
    },
    scales: {
        x: {
            grid: {
                display: false,
            },
        },
        y: {
            grid: {
                display: false,
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
</script>

<template>
    <Head title="DevTrack Analytics" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header with Period Selector -->
            <div class="flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-center">
                <div>
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-cyan-500 bg-clip-text text-transparent">
                        Development Analytics
                    </h1>
                    <p class="text-muted-foreground mt-1">Track your Claude Code productivity and costs</p>
                </div>
                <div class="flex gap-1 bg-secondary/50 p-1 rounded-lg">
                    <Button
                        v-for="period in periods"
                        :key="period.value"
                        :variant="selectedPeriod === period.value ? 'default' : 'ghost'"
                        size="sm"
                        @click="selectedPeriod = period.value"
                        class="gap-2"
                    >
                        <component :is="period.icon" class="h-3 w-3" />
                        {{ period.label }}
                    </Button>
                </div>
            </div>

            <!-- Key Metrics Cards with Icons -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                <Card class="border-l-4 border-l-purple-500">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-sm font-medium">Total Investment</CardTitle>
                            <DollarSign class="h-4 w-4 text-purple-500" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">${{ stats.summary.total_cost.toFixed(2) }}</div>
                        <p class="text-xs text-muted-foreground flex items-center gap-1 mt-1">
                            <Zap class="h-3 w-3" />
                            {{ formatNumber(stats.summary.total_tokens) }} tokens
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-l-4 border-l-cyan-500">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-sm font-medium">Code Generated</CardTitle>
                            <Code2 class="h-4 w-4 text-cyan-500" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatNumber(stats.summary.total_lines_written) }}</div>
                        <p class="text-xs text-muted-foreground">
                            Lines of code
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-l-4 border-l-green-500">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-sm font-medium">Time Invested</CardTitle>
                            <Clock class="h-4 w-4 text-green-500" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.summary.total_time_hours }}h</div>
                        <p class="text-xs text-muted-foreground">
                            Development hours
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-l-4 border-l-yellow-500">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-sm font-medium">Sessions</CardTitle>
                            <Activity class="h-4 w-4 text-yellow-500" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.summary.total_sessions }}</div>
                        <p class="text-xs text-muted-foreground">
                            ~{{ stats.summary.avg_session_minutes }} min avg
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-l-4 border-l-pink-500">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-sm font-medium">Productivity</CardTitle>
                            <TrendingUp class="h-4 w-4 text-pink-500" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-baseline gap-2">
                            <div class="text-2xl font-bold">{{ productivityScore }}%</div>
                            <span :class="getProductivityColor(productivityScore)" class="text-xs font-medium">
                                {{ getProductivityLabel(productivityScore) }}
                            </span>
                        </div>
                        <div class="mt-2 h-2 bg-secondary rounded-full overflow-hidden">
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
                    <div class="h-80">
                        <Line :data="activityChartData" :options="activityChartOptions" />
                    </div>
                </CardContent>
            </Card>

            <!-- Analytics Grid -->
            <div class="grid gap-6 lg:grid-cols-3">
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
                <Card class="lg:col-span-2">
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
                                    <th class="text-left py-3 font-medium text-muted-foreground">Task</th>
                                    <th class="text-left py-3 font-medium text-muted-foreground">Developer</th>
                                    <th class="text-left py-3 font-medium text-muted-foreground">Project</th>
                                    <th class="text-left py-3 font-medium text-muted-foreground">When</th>
                                    <th class="text-right py-3 font-medium text-muted-foreground">Duration</th>
                                    <th class="text-right py-3 font-medium text-muted-foreground">Output</th>
                                    <th class="text-right py-3 font-medium text-muted-foreground">Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="session in stats.recent_sessions" :key="session.id" 
                                    class="border-b border-border/30 hover:bg-secondary/30 transition-colors">
                                    <td class="py-3 max-w-xs">
                                        <div class="truncate font-medium">{{ session.task }}</div>
                                    </td>
                                    <td class="py-3">
                                        <span class="text-muted-foreground">{{ session.developer }}</span>
                                    </td>
                                    <td class="py-3">
                                        <span class="inline-flex items-center gap-1 text-xs bg-secondary px-2 py-1 rounded-md">
                                            <GitBranch class="h-3 w-3" />
                                            {{ session.project }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-muted-foreground">{{ session.started_at }}</td>
                                    <td class="py-3 text-right">
                                        <span class="inline-flex items-center gap-1">
                                            <Clock class="h-3 w-3 text-muted-foreground" />
                                            {{ session.duration }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-right">
                                        <span class="font-mono text-xs bg-gradient-to-r from-purple-600 to-cyan-600 bg-clip-text text-transparent font-semibold">
                                            {{ session.lines_written }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-right font-medium">{{ session.cost }}</td>
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
                        <div v-for="(file, index) in stats.top_files.slice(0, 5)" :key="file.path" 
                             class="flex items-center gap-4 p-3 rounded-lg hover:bg-secondary/50 transition-colors">
                            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500 to-cyan-500 flex items-center justify-center text-white font-bold">
                                {{ index + 1 }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-mono text-sm font-medium truncate">{{ file.name }}</div>
                                <div class="font-mono text-xs text-muted-foreground truncate">{{ file.path }}</div>
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <div class="text-sm font-semibold">{{ file.lines }} lines</div>
                                <div class="text-xs text-muted-foreground">{{ file.edits }} edits</div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>