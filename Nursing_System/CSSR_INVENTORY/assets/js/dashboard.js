(() => {
  'use strict';
  const source = document.getElementById('cu-dashboard-data');
  if (!source) return;
  const data = JSON.parse(source.textContent);
  const form = document.querySelector('[data-dashboard-year]');
  form?.querySelector('select')?.addEventListener('change', () => form.requestSubmit());
  const tokens = getComputedStyle(document.documentElement);
  const color = name => tokens.getPropertyValue(name).trim();
  const namespace = 'http://www.w3.org/2000/svg';
  const number = value => Number(value).toLocaleString('en-PH');
  function element(tag, attributes = {}, text = '') {
    const node = document.createElementNS(namespace, tag);
    Object.entries(attributes).forEach(([name,value]) => node.setAttribute(name, String(value)));
    if (text) node.textContent = text;
    return node;
  }
  function makeSvg(target, width, height) {
    target.replaceChildren();
    const svg = element('svg', {viewBox: '0 0 ' + width + ' ' + height, width: '100%', height: '100%', 'aria-hidden': 'true'});
    target.append(svg);
    return svg;
  }
  const bar = document.querySelector('#cu-issuance-chart');
  function renderIssuance() {
    if (!bar) return;
    const width = Math.max(200, bar.clientWidth), height = 230;
    const svg = makeSvg(bar, width, height);
    const maximum = Math.max(...data.monthly, 0);
    const magnitude = 10 ** Math.floor(Math.log10(Math.max(maximum / 4, 1)));
    const rawStep = Math.max(maximum / 4, 1) / magnitude;
    const step = ([1,2,5,10].find(value => value >= rawStep) || 10) * magnitude;
    const ceiling = Math.max(step, Math.ceil(maximum / step) * step);
    const left = ceiling >= 1000000 ? 68 : 48, top = 12, bottom = 30;
    const plotWidth = width - left - 4, plotHeight = height - top - bottom;
    for (let tick = 0; tick <= ceiling; tick += step) {
      const y = top + plotHeight - (tick / ceiling * plotHeight);
      svg.append(element('line', {x1:left,y1:y,x2:width,y2:y,stroke:color('--cu-line'),'stroke-width':1}));
      svg.append(element('text', {x:left-10,y:y+4,'text-anchor':'end',fill:color('--cu-muted'),'font-size':11}, number(tick)));
    }
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const slot = plotWidth / 12, barWidth = Math.min(28, slot * .6);
    data.monthly.forEach((value,index) => {
      const x = left + slot * index + (slot - barWidth) / 2;
      const barHeight = value / ceiling * plotHeight;
      const group = element('g', {'data-month':index + 1,'data-value':value});
      group.append(element('title', {}, months[index] + ': ' + number(value) + ' units issued'));
      group.append(element('rect', {x,y:top+plotHeight-barHeight,width:barWidth,height:barHeight,rx:Math.min(4,barHeight/2),fill:color('--cu-teal'),class:'cu-issuance-bar'}));
      svg.append(group);
      if (width >= 450 || index % 2 === 0) svg.append(element('text', {x:left+slot*(index+.5),y:height-8,'text-anchor':'middle',fill:color('--cu-muted'),'font-size':11}, months[index]));
    });
    bar.dataset.chartTotal = data.monthly.reduce((sum,value) => sum + value, 0);
  }
  renderIssuance();
  if (bar) new ResizeObserver(renderIssuance).observe(bar);
  const donut = document.querySelector('#cu-stock-chart');
  function renderStock() {
    if (!donut) return;
    const svg = makeSvg(donut, 188, 188);
    const total = data.stock.reduce((sum,value) => sum + value, 0);
    const circumference = 2 * Math.PI * 80;
    svg.append(element('circle', {cx:94,cy:94,r:80,fill:'none',stroke:color('--cu-line'),'stroke-width':18}));
    let offset = 0;
    const labels = ['Available','Low Stock','Out of Stock'];
    const colors = [color('--cu-green'), color('--cu-warning'), color('--cu-danger')];
    data.stock.forEach((value,index) => {
      if (!value || !total) return;
      const arc = value / total * circumference;
      const gap = value === total ? 0 : Math.min(4,arc*.15);
      const segment = element('circle', {cx:94,cy:94,r:80,fill:'none',stroke:colors[index],'stroke-width':18,
        'stroke-dasharray':Math.max(0,arc-gap) + ' ' + circumference,'stroke-dashoffset':-offset,transform:'rotate(-90 94 94)','data-stock-value':value});
      segment.append(element('title', {}, labels[index] + ': ' + number(value) + ' items'));
      svg.append(segment); offset += arc;
    });
    donut.dataset.chartTotal = total;
    donut.dataset.empty = String(total === 0);
  }
  renderStock();
  const redraw=()=>{renderIssuance();renderStock();};
  window.addEventListener('cssr:themechange',redraw);
  window.addEventListener('beforeprint',redraw);
  window.addEventListener('afterprint',redraw);
  matchMedia('print').addEventListener('change',redraw);
})();
