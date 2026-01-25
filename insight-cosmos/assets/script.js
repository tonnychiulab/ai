document.addEventListener('DOMContentLoaded', function() {
    const { root, nonce } = icConfig;
    let cy;

    // 通用 Fetch 函式
    async function api(path, method = 'GET', body = null) {
        const response = await fetch(`${root}${path}`, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
            body: body ? JSON.stringify(body) : null
        });
        return response.json();
    }

    // 初始化畫布
    async function initGraph() {
        const data = await api('/graph');
        
        const elements = [
            ...data.nodes.map(n => ({ data: { id: n.id, label: n.label } })),
            ...data.edges.map(e => ({ data: { id: `e${e.id}`, source: e.source_id, target: e.target_id } }))
        ];

        cy = cytoscape({
            container: document.getElementById('ic-canvas'),
            elements: elements,
            style: [
                { selector: 'node', style: { 'label': 'data(label)', 'background-color': '#0073aa', 'color': '#333' } },
                { selector: 'edge', style: { 'width': 2, 'line-color': '#ccc', 'target-arrow-shape': 'triangle' } }
            ],
            layout: { name: 'cose' }
        });

        cy.on('tap', 'node', e => showDetails(e.target.data()));
    }

    // 顯示側邊欄資訊
    function showDetails(node) {
        const panel = document.getElementById('ic-panel-content');
        panel.innerHTML = `
            <h3>${node.label}</h3>
            <p>ID: ${node.id}</p>
            <button class="button button-link-delete" id="del-node" data-id="${node.id}">刪除節點</button>
        `;

        document.getElementById('del-node').onclick = async function() {
            if(confirm('確定刪除?')) {
                await api(`/node/${this.dataset.id}`, 'DELETE');
                initGraph(); // 重新整理
            }
        };
    }

    // 新增節點按鈕
    document.getElementById('ic-add-node').onclick = async function() {
        const label = prompt('請輸入節點名稱:');
        if (label) {
            await api('/node', 'POST', { label });
            initGraph();
        }
    };

    initGraph();
});