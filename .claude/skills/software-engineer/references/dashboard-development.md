# Dashboard Development

## Dashboard Design Principles

### Visual Hierarchy

```
┌─────────────────────────────────────────────────────────┐
│  Header: Title, Date Range Picker, Filters, Export      │
├────────────┬────────────┬────────────┬──────────────────┤
│   KPI 1    │   KPI 2    │   KPI 3    │     KPI 4        │  ← Top: Key Metrics
├────────────┴────────────┴────────────┴──────────────────┤
│                                                         │
│  Primary Chart: Trend Line / Area Chart                 │  ← Middle: Trends
│                                                         │
├──────────────────────────┬──────────────────────────────┤
│  Secondary Chart         │  Secondary Chart             │  ← Lower: Details
│  (Bar/Pie)               │  (Table/Heatmap)             │
├──────────────────────────┴──────────────────────────────┤
│  Data Table: Detailed Records with Search & Sort        │  ← Bottom: Raw Data
└─────────────────────────────────────────────────────────┘
```

### Data-Ink Ratio (Edward Tufte)
- Maximize data representation, minimize decoration
- Remove gridlines (or use very light ones)
- Remove chart borders and unnecessary axes
- Use direct labels instead of legends when possible
- Avoid 3D effects, gradients, and shadows on charts

### Chart Selection Guide

| Data Relationship | Chart Type | When to Use |
|-------------------|------------|-------------|
| Comparison | Bar (vertical/horizontal) | Compare categories or groups |
| Trend over time | Line / Area | Show changes over time periods |
| Proportion | Donut / Pie | Show part-to-whole (≤6 segments) |
| Distribution | Histogram / Box plot | Show data spread and outliers |
| Correlation | Scatter / Bubble | Show relationships between variables |
| Composition | Stacked Bar / Area | Show composition changes over time |
| Geographic | Choropleth / Pin map | Location-based data |
| Flow | Sankey / Funnel | Show process flows and conversions |
| Ranking | Horizontal Bar | Ordered comparison |
| KPI | Number + Sparkline | Single metric with trend indicator |

---

## KPI Card Pattern

```typescript
interface KPICardProps {
  title: string;
  value: number | string;
  format: 'number' | 'currency' | 'percentage';
  trend: {
    value: number;      // Percentage change
    direction: 'up' | 'down' | 'flat';
    isPositive: boolean; // Is the direction good or bad?
  };
  sparklineData?: number[];
  loading?: boolean;
}

// Display:
// ┌──────────────────┐
// │ Total Revenue     │
// │ $1,234,567   ↑12% │
// │ ▁▂▃▅▆▇█▆▅▇       │
// └──────────────────┘
```

---

## Visualization Libraries

### JavaScript/TypeScript

| Library | Best For | Size | Learning Curve |
|---------|----------|------|----------------|
| **Recharts** | React dashboards, simple charts | 40KB | Low |
| **Chart.js** | Quick, responsive charts | 60KB | Low |
| **D3.js** | Custom, complex visualizations | 80KB | High |
| **Nivo** | React, beautiful defaults | 50KB | Medium |
| **ECharts** | Large datasets, interactive maps | 100KB | Medium |
| **Plotly** | Scientific/data analysis | 150KB | Medium |
| **Tremor** | React dashboard components | 30KB | Low |
| **shadcn/ui Charts** | Tailwind-based, modern React | Varies | Low |

### Recommendation by Use Case
- **Admin Dashboard**: Tremor or shadcn/ui + Recharts
- **Analytics Dashboard**: ECharts or Nivo
- **Data Exploration**: Plotly or D3.js
- **Simple Metrics**: Chart.js or Recharts

---

## Responsive Dashboard Layout

### CSS Grid Dashboard

```css
.dashboard {
  display: grid;
  gap: 1rem;
  padding: 1rem;
  grid-template-columns: repeat(4, 1fr);
}

.kpi-card { grid-column: span 1; }
.chart-primary { grid-column: span 4; }
.chart-secondary { grid-column: span 2; }
.data-table { grid-column: span 4; }

/* Tablet */
@media (max-width: 1024px) {
  .dashboard { grid-template-columns: repeat(2, 1fr); }
  .chart-primary { grid-column: span 2; }
  .chart-secondary { grid-column: span 2; }
}

/* Mobile */
@media (max-width: 768px) {
  .dashboard { grid-template-columns: 1fr; }
  .kpi-card, .chart-primary, .chart-secondary, .data-table {
    grid-column: span 1;
  }
}
```

---

## Real-Time Data Patterns

### WebSocket (Recommended for Low Latency)

```typescript
// Server (Node.js with ws)
const wss = new WebSocketServer({ port: 8080 });
wss.on('connection', (ws) => {
  const interval = setInterval(() => {
    ws.send(JSON.stringify({
      type: 'metrics_update',
      data: getLatestMetrics(),
      timestamp: Date.now(),
    }));
  }, 1000);

  ws.on('close', () => clearInterval(interval));
});

// Client (React hook)
function useRealtimeMetrics(url: string) {
  const [metrics, setMetrics] = useState<Metrics | null>(null);

  useEffect(() => {
    const ws = new WebSocket(url);
    ws.onmessage = (event) => {
      const { data } = JSON.parse(event.data);
      setMetrics(data);
    };
    ws.onclose = () => {
      // Reconnect with exponential backoff
      setTimeout(() => reconnect(), backoff);
    };
    return () => ws.close();
  }, [url]);

  return metrics;
}
```

### Server-Sent Events (SSE) - Simpler, One-Way

```typescript
// Server
app.get('/api/stream/metrics', (req, res) => {
  res.setHeader('Content-Type', 'text/event-stream');
  res.setHeader('Cache-Control', 'no-cache');
  res.setHeader('Connection', 'keep-alive');

  const interval = setInterval(() => {
    res.write(`data: ${JSON.stringify(getMetrics())}\n\n`);
  }, 5000);

  req.on('close', () => clearInterval(interval));
});

// Client
const source = new EventSource('/api/stream/metrics');
source.onmessage = (event) => {
  updateDashboard(JSON.parse(event.data));
};
```

### Polling (Fallback)

```typescript
// Use polling when:
// - Data doesn't need sub-second updates
// - Infrastructure doesn't support WebSocket/SSE
// - Simple implementation preferred

function usePolling<T>(url: string, intervalMs: number = 30000) {
  const [data, setData] = useState<T | null>(null);

  useEffect(() => {
    const fetchData = async () => {
      const response = await fetch(url);
      setData(await response.json());
    };
    fetchData(); // Initial fetch
    const interval = setInterval(fetchData, intervalMs);
    return () => clearInterval(interval);
  }, [url, intervalMs]);

  return data;
}
```

### Update Frequency Guide

| Data Type | Update Frequency | Method |
|-----------|-----------------|--------|
| Stock prices, live scores | <1s | WebSocket |
| Server metrics, logs | 1-5s | WebSocket or SSE |
| Sales dashboard | 30s-5min | SSE or Polling |
| Weekly reports | On demand | Polling or manual refresh |

---

## Dashboard Accessibility

| Requirement | Implementation |
|-------------|----------------|
| Color-blind safe | Use patterns + colors; test with Coblis simulator |
| Screen readers | Provide data tables alongside charts |
| Keyboard navigation | Tab through interactive elements, arrow keys within charts |
| High contrast | Ensure 4.5:1 ratio for text, 3:1 for UI elements |
| Alt text | Describe chart meaning, not just "bar chart" |
| Reduced motion | Respect `prefers-reduced-motion` media query |

### Color-Blind Safe Palettes

```css
/* IBM Design palette (color-blind safe) */
--color-1: #648FFF;  /* Blue */
--color-2: #785EF0;  /* Purple */
--color-3: #DC267F;  /* Magenta */
--color-4: #FE6100;  /* Orange */
--color-5: #FFB000;  /* Yellow */

/* Pair colors with shapes/patterns for additional differentiation */
```

---

## Dashboard Performance

| Technique | Implementation |
|-----------|----------------|
| Virtualize large tables | react-window, TanStack Virtual |
| Lazy load charts | Intersection Observer, below-fold charts |
| Aggregate server-side | Pre-compute summaries, don't send raw data |
| Canvas rendering | Use Canvas for >10K data points |
| Web Workers | Process data transformations off main thread |
| Pagination | Paginate data tables, limit chart data points |
| Caching | Cache dashboard data with SWR/React Query |

---

## Sources
- Edward Tufte: The Visual Display of Quantitative Information
- Stephen Few: Information Dashboard Design
- WCAG 2.1 AA Guidelines
- web.dev: Performance best practices
