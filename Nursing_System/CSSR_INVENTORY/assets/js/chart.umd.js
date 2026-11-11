(function () {
    var charts = new Map();
    function ink(light, token) {
        if (document.documentElement.dataset.cuTheme !== 'dark' || matchMedia('print').matches) return light;
        return getComputedStyle(document.documentElement).getPropertyValue(token || '--cu-text').trim() || '#e5edf5';
    }
    function redraw() { charts.forEach(function(draw, canvas) { if(canvas.isConnected)draw();else charts.delete(canvas); }); }
    window.addEventListener('cssr:themechange', redraw);
    window.addEventListener('resize', redraw);
    window.addEventListener('beforeprint', redraw);
    window.addEventListener('afterprint', redraw);
    matchMedia('print').addEventListener('change', redraw);
    function colorAt(color, index, fallback) {
        var value=Array.isArray(color) ? (color[index] || fallback) : (color || fallback);
        // Retain each series hue while lifting dark legacy bar colors off the dark surface.
        if(document.documentElement.dataset.cuTheme==='dark'&&!matchMedia('print').matches&&/^#[0-9a-f]{6}$/i.test(value)){
            value='rgb('+[1,3,5].map(function(offset){return Math.round(parseInt(value.slice(offset,offset+2),16)*.65+255*.35);}).join(',')+')';
        }
        return value;
    }

    function shortLabel(value, limit) {
        value = String(value || '');
        return value.length > limit ? value.slice(0, limit - 1) + '…' : value;
    }

    function drawHorizontal(ctx, canvas, labels, datasets) {
        var width = canvas.width = canvas.clientWidth || 900;
        var height = canvas.height = canvas.clientHeight || 680;
        var left = Math.min(310, Math.max(180, width * 0.28));
        var right = 58;
        var top = 48;
        var bottom = 34;
        var plotWidth = Math.max(100, width - left - right);
        var rowHeight = Math.max(20, (height - top - bottom) / Math.max(1, labels.length));
        var values = [];

        datasets.forEach(function (dataset) {
            (dataset.data || []).forEach(function (value) {
                values.push(Number(value) || 0);
            });
        });

        var max = Math.max.apply(Math, [1].concat(values));
        var roundedMax = Math.ceil(max / Math.pow(10, Math.max(0, String(Math.floor(max)).length - 1))) * Math.pow(10, Math.max(0, String(Math.floor(max)).length - 1));
        max = Math.max(max, roundedMax);

        ctx.clearRect(0, 0, width, height);
        ctx.font = '11px Arial';
        ctx.textBaseline = 'middle';

        for (var tick = 0; tick <= 5; tick++) {
            var gridX = left + (plotWidth * tick / 5);
            ctx.strokeStyle = ink('#d9e2ec','--cu-line');
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(gridX, top - 4);
            ctx.lineTo(gridX, height - bottom);
            ctx.stroke();
            ctx.fillStyle = ink('#536273','--cu-muted');
            ctx.textAlign = 'center';
            ctx.fillText(Math.round(max * tick / 5).toLocaleString(), gridX, height - 14);
        }

        var legendX = left;
        datasets.forEach(function (dataset, datasetIndex) {
            var color = colorAt(dataset.backgroundColor, datasetIndex, datasetIndex ? '#6047aa' : '#df6666');
            ctx.fillStyle = color;
            ctx.fillRect(legendX, 12, 11, 11);
            ctx.fillStyle = ink('#364152');
            ctx.textAlign = 'left';
            ctx.fillText(dataset.label || ('Series ' + (datasetIndex + 1)), legendX + 16, 18);
            legendX += ctx.measureText(dataset.label || '').width + 54;
        });

        labels.forEach(function (label, index) {
            var rowTop = top + index * rowHeight;
            var barGap = 2;
            var availableHeight = Math.max(8, rowHeight - 5);
            var barHeight = Math.max(4, (availableHeight - barGap * (datasets.length - 1)) / Math.max(1, datasets.length));

            ctx.fillStyle = ink('#172b4d');
            ctx.font = 'bold 10px Arial';
            ctx.textAlign = 'right';
            ctx.fillText(shortLabel(label, 38), left - 10, rowTop + availableHeight / 2);

            datasets.forEach(function (dataset, datasetIndex) {
                var value = Number((dataset.data || [])[index]) || 0;
                var barWidth = value / max * plotWidth;
                var y = rowTop + datasetIndex * (barHeight + barGap);
                ctx.fillStyle = colorAt(dataset.backgroundColor, index, datasetIndex ? '#6047aa' : '#df6666');
                ctx.fillRect(left, y, barWidth, barHeight);
                ctx.fillStyle = ink(datasetIndex ? '#3f3279' : '#b84646');
                ctx.font = '10px Arial';
                ctx.textAlign = 'left';
                ctx.fillText(value.toLocaleString(), Math.min(width - right + 3, left + barWidth + 5), y + barHeight / 2);
            });
        });
    }

    function drawVertical(ctx, canvas, labels, datasets) {
        var width = canvas.width = canvas.clientWidth || 500;
        var height = canvas.height = canvas.clientHeight || 260;
        var values = (datasets[0] && datasets[0].data) || [];
        var max = Math.max.apply(Math, [1].concat(values.map(Number)));
        var barWidth = Math.max(16, (width - 60) / Math.max(1, values.length) - 10);

        ctx.clearRect(0, 0, width, height);
        ctx.font = '12px Arial';
        values.forEach(function (value, index) {
            var barHeight = (Number(value) / max) * (height - 70);
            var x = 40 + index * (barWidth + 10);
            var y = height - 35 - barHeight;
            ctx.fillStyle = colorAt(datasets[0].backgroundColor, index, '#0b5f9f');
            ctx.fillRect(x, y, barWidth, barHeight);
            ctx.fillStyle = ink('#1d2733');
            ctx.fillText(String(value), x, y - 5);
            ctx.save();
            ctx.translate(x, height - 18);
            ctx.rotate(-0.45);
            ctx.fillStyle = ink('#64748b','--cu-muted');
            ctx.fillText(labels[index] || '', 0, 0);
            ctx.restore();
        });
    }

    window.Chart = function (context, config) {
        var ctx = context.canvas ? context : context.getContext('2d');
        var canvas = ctx.canvas;
        var labels = (config.data && config.data.labels) || [];
        var datasets = (config.data && config.data.datasets) || [];
        function draw() {
        if (!datasets.length) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = ink('#64748b','--cu-muted');
            ctx.fillText('No data', 20, 30);
            return;
        }

        if (config.options && config.options.indexAxis === 'y') {
            drawHorizontal(ctx, canvas, labels, datasets);
        } else {
            drawVertical(ctx, canvas, labels, datasets);
        }
        }
        charts.set(canvas,draw);draw();
        this.update=draw;this.destroy=function(){charts.delete(canvas);};
    };
})();
