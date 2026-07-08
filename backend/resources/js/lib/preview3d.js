// 3D product preview for the Review step (Babylon.js).
//
// The fabric-canvas export (the review preview image) becomes a texture on a
// 3D model of the product. Flat products get their geometry GENERATED from the
// surface spec — trim ratio, die-cut outline (holes included: door hangers),
// thickness — so every die shape previews without hand-built models. A few
// product families get bespoke procedural builders (cloth that waves for
// banners, a mug, a tote, a tee). No downloaded assets: every mesh is built
// in code (no CC0 mug/shirt models exist on the usual open libraries anyway).
//
//   const preview = await mountPreview3D(canvasEl, { kind, spec, texture });
//   preview.dispose();
//
// kind: 'slab' | 'cloth' | 'mug' | 'tote' | 'shirt'  (detectKind helps)
// spec:  the PrintSpec canvas payload — { trimW, trimH, cut, ... } (slab/cloth)
// texture: URL or data-URL of the design preview (same-origin / data)

import {
    ArcRotateCamera, Color3, Color4, DirectionalLight, Engine, HemisphericLight,
    Mesh, MeshBuilder, Scene, ShadowGenerator, StandardMaterial, Texture,
    TransformNode, Vector3, VertexBuffer,
} from '@babylonjs/core';
import earcut from 'earcut';

/** Pick a model family from the product; 'slab' covers every flat print. */
export function detectKind(product = {}, category = {}) {
    const n = `${product.slug || ''} ${product.name || ''}`.toLowerCase();
    if (/\bmug|cup\b/.test(n)) return 'mug';
    if (/tote|canvas-bag|shopping bag/.test(n)) return 'tote';
    if (/t-?shirt|hoodie|sweatshirt|polo/.test(n)) return 'shirt';
    if (/banner|tablecloth|backdrop|table runner/.test(n) && !/frame/.test(n)) return 'cloth';
    return 'slab';
}

export async function mountPreview3D(canvas, { kind = 'slab', spec = {}, texture = null } = {}) {
    const engine = new Engine(canvas, true, { preserveDrawingBuffer: true, stencil: false, antialias: true });
    const scene = new Scene(engine);
    scene.clearColor = new Color4(1, 1, 1, 1); // the review card is white

    // slight 3/4 product-shot pose, near eye level
    const camera = new ArcRotateCamera('cam', -Math.PI / 2 + 0.35, 1.32, 2.0, new Vector3(0, 0, 0), scene);
    camera.attachControl(canvas, true);
    camera.minZ = 0.05;
    camera.lowerRadiusLimit = 0.8;
    camera.upperRadiusLimit = 4;
    camera.upperBetaLimit = Math.PI / 2.05; // never under the ground
    camera.wheelDeltaPercentage = 0.01;
    camera.pinchDeltaPercentage = 0.01;
    camera.panningSensibility = 0; // orbit only
    camera.useAutoRotationBehavior = true;
    camera.autoRotationBehavior.idleRotationSpeed = 0.09;
    camera.autoRotationBehavior.idleRotationWaitTime = 4000;
    camera.autoRotationBehavior.idleRotationSpinupTime = 2000;

    new HemisphericLight('hemi', new Vector3(0, 1, 0), scene).intensity = 0.85;
    const sun = new DirectionalLight('sun', new Vector3(-0.35, -1, -0.4), scene);
    sun.position = new Vector3(0.8, 2.4, 1);
    sun.intensity = 0.55;

    // soft ground shadow anchors the object; white ground melts into the card
    const ground = MeshBuilder.CreateGround('ground', { width: 8, height: 8 }, scene);
    ground.position.y = -0.52;
    const groundMat = new StandardMaterial('groundMat', scene);
    groundMat.diffuseColor = Color3.White();
    groundMat.specularColor = Color3.Black();
    ground.material = groundMat;
    ground.receiveShadows = true;
    const shadows = new ShadowGenerator(1024, sun);
    shadows.useBlurExponentialShadowMap = true;
    shadows.blurKernel = 32;
    shadows.setDarkness(0.82);

    const designTex = texture ? new Texture(texture, scene, false, true) : null; // invertY=true: image top = UV v1
    const cast = BUILDERS[kind] ? BUILDERS[kind](scene, spec, designTex) : BUILDERS.slab(scene, spec, designTex);
    cast.forEach((m) => shadows.addShadowCaster(m));

    // frame the object: aim at its center, back off proportionally to its size
    cast.forEach((m) => m.computeWorldMatrix(true));
    const ext = scene.getWorldExtends((m) => cast.includes(m));
    const size = ext.max.subtract(ext.min);
    camera.setTarget(ext.min.add(size.scale(0.5)));
    camera.radius = Math.min(3.2, Math.max(1.05, Math.max(size.x, size.y * 1.5, size.z) * 1.28 + 0.22));

    engine.runRenderLoop(() => scene.render());
    const onResize = () => engine.resize();
    window.addEventListener('resize', onResize);

    return {
        dispose() {
            window.removeEventListener('resize', onResize);
            engine.stopRenderLoop();
            scene.dispose();
            engine.dispose();
        },
    };
}

/* ------------------------------------------------------------------------- */

/** Materials */
function designMaterial(scene, tex) {
    const m = new StandardMaterial('design', scene);
    m.specularColor = new Color3(0.06, 0.06, 0.06);
    if (tex) m.diffuseTexture = tex;
    else m.diffuseColor = new Color3(0.93, 0.92, 0.9);
    return m;
}
function bodyMaterial(scene, hex = '#f4f2ec') {
    const m = new StandardMaterial('body', scene);
    m.diffuseColor = Color3.FromHexString(hex);
    m.specularColor = new Color3(0.04, 0.04, 0.04);
    return m;
}

/**
 * Sample the normalized (0–100, y-down) die-cut path into polygon subpaths.
 * Handles the absolute commands our surfaces use (M L H V C S Q T A Z, arcs
 * rotation-0). Returns [ [ {x,y}… ] … ]; null → caller falls back to a rect.
 */
function samplePath(d) {
    if (!d || /[a-z]/.test(d)) return null;
    const subs = [];
    let pts = null;
    let cx = 0;
    let cy = 0;
    const N = 14; // samples per curve segment
    const push = (x, y) => { pts && pts.push({ x, y }); cx = x; cy = y; };
    for (const [, cmd, rest] of d.matchAll(/([MLHVCSQTAZ])([^MLHVCSQTAZ]*)/g)) {
        const n = (rest.match(/-?\d*\.?\d+/g) || []).map(Number);
        if (cmd === 'M') { pts = []; subs.push(pts); for (let i = 0; i + 1 < n.length; i += 2) push(n[i], n[i + 1]); }
        else if (cmd === 'L' || cmd === 'T') for (let i = 0; i + 1 < n.length; i += 2) push(n[i], n[i + 1]);
        else if (cmd === 'H') n.forEach((x) => push(x, cy));
        else if (cmd === 'V') n.forEach((y) => push(cx, y));
        else if (cmd === 'C') for (let i = 0; i + 5 < n.length; i += 6) sampleCubic(n.slice(i, i + 6));
        else if (cmd === 'S' || cmd === 'Q') for (let i = 0; i + 3 < n.length; i += 4) sampleQuad(n.slice(i, i + 4));
        else if (cmd === 'A') for (let i = 0; i + 6 < n.length; i += 7) sampleArc(n.slice(i, i + 7));
        // Z: polygon closes implicitly
    }
    function sampleCubic([x1, y1, x2, y2, x, y]) {
        const [sx, sy] = [cx, cy];
        for (let t = 1; t <= N; t++) {
            const u = t / N; const v = 1 - u;
            push(v * v * v * sx + 3 * v * v * u * x1 + 3 * v * u * u * x2 + u * u * u * x,
                v * v * v * sy + 3 * v * v * u * y1 + 3 * v * u * u * y2 + u * u * u * y);
        }
    }
    function sampleQuad([x1, y1, x, y]) {
        const [sx, sy] = [cx, cy];
        for (let t = 1; t <= N; t++) {
            const u = t / N; const v = 1 - u;
            push(v * v * sx + 2 * v * u * x1 + u * u * x, v * v * sy + 2 * v * u * y1 + u * u * y);
        }
    }
    function sampleArc([rx, ry, rot, laf, sf, x, y]) {
        if (rot) { push(x, y); return; }
        rx = Math.abs(rx) || 0.01; ry = Math.abs(ry) || 0.01;
        const mx = (cx - x) / 2; const my = (cy - y) / 2;
        const lam = (mx * mx) / (rx * rx) + (my * my) / (ry * ry);
        if (lam > 1) { const s = Math.sqrt(lam); rx *= s; ry *= s; }
        const den = rx * rx * my * my + ry * ry * mx * mx;
        let c = den > 1e-9 ? Math.sqrt(Math.max(0, (rx * rx * ry * ry - den) / den)) : 0;
        if (laf === sf) c = -c;
        const ecx = (c * rx * my) / ry + (cx + x) / 2;
        const ecy = (-c * ry * mx) / rx + (cy + y) / 2;
        const t1 = Math.atan2((cy - ecy) / ry, (cx - ecx) / rx);
        let dt = Math.atan2((y - ecy) / ry, (x - ecx) / rx) - t1;
        if (!sf && dt > 0) dt -= 2 * Math.PI;
        if (sf && dt < 0) dt += 2 * Math.PI;
        for (let t = 1; t <= N; t++) {
            const a = t1 + (dt * t) / N;
            push(ecx + rx * Math.cos(a), ecy + ry * Math.sin(a));
        }
    }
    return subs.map((s) => dedupe(s)).filter((s) => s.length >= 3);
}
function dedupe(pts) {
    const out = [];
    for (const p of pts) {
        const l = out[out.length - 1];
        if (!l || Math.abs(l.x - p.x) > 0.05 || Math.abs(l.y - p.y) > 0.05) out.push(p);
    }
    if (out.length > 1) {
        const [f, l] = [out[0], out[out.length - 1]];
        if (Math.abs(f.x - l.x) < 0.05 && Math.abs(f.y - l.y) < 0.05) out.pop();
    }
    return out;
}
const ringArea = (pts) => Math.abs(pts.reduce((a, p, i) => {
    const q = pts[(i + 1) % pts.length];
    return a + (p.x * q.y - q.x * p.y);
}, 0)) / 2;

/**
 * Flat product: a thin slab in the exact die shape (holes stay holes) with the
 * design on top and paper-coloured body — generated purely from the spec.
 */
function slab(scene, spec, tex, { thickness = 0.035, y = 0 } = {}) {
    const tw = Number(spec.trimW) || 760;
    const th = Number(spec.trimH) || 434;
    const long = Math.max(tw, th);
    const w = tw / long;
    const h = th / long;

    let rings = samplePath(spec.cut || null) || [[{ x: 0, y: 0 }, { x: 100, y: 0 }, { x: 100, y: 100 }, { x: 0, y: 100 }]];
    rings = rings.sort((a, b) => ringArea(b) - ringArea(a));
    // normalized 0–100 (y down) → meters in XZ, centered (z flipped: y-down → z-up)
    const toXZ = (p) => new Vector3((p.x / 100 - 0.5) * w, 0, (0.5 - p.y / 100) * h);
    const outline = rings[0].map(toXZ);
    const holes = rings.slice(1).map((r) => r.map(toXZ));

    const root = new TransformNode('slab', scene);
    const body = MeshBuilder.ExtrudePolygon('slab-body', { shape: outline, holes, depth: thickness }, scene, earcut);
    body.material = bodyMaterial(scene);
    body.position.y = y + thickness;
    body.parent = root;

    const face = MeshBuilder.CreatePolygon('slab-face', { shape: outline, holes }, scene, earcut);
    remapTopUVs(face, w, h);
    face.material = designMaterial(scene, tex);
    face.position.y = y + thickness + 0.0015;
    face.parent = root;

    // stand it upright facing the camera, leaning back a touch (product shot);
    // rotation.x = -π/2 maps design-top (+Z) up and the face normal (+Y) to -Z
    root.rotation.x = -Math.PI / 2 + 0.1;
    root.position.y = h / 2 - 0.49;

    return [body, face];
}

/** The polygon cap's UVs must map the TRIM BOX (not the polygon bbox) onto the design. */
function remapTopUVs(mesh, w, h) {
    const pos = mesh.getVerticesData(VertexBuffer.PositionKind);
    const uv = mesh.getVerticesData(VertexBuffer.UVKind);
    if (!pos || !uv) return;
    for (let i = 0, j = 0; i < pos.length; i += 3, j += 2) {
        uv[j] = pos[i] / w + 0.5;          // x → u
        uv[j + 1] = pos[i + 2] / h + 0.5;  // z → v (z already flipped at build)
    }
    mesh.setVerticesData(VertexBuffer.UVKind, uv, true);
}

/** Banner/tablecloth: a standing cloth that waves gently, printed both sides. */
function cloth(scene, spec, tex) {
    const tw = Number(spec.trimW) || 760;
    const th = Number(spec.trimH) || 380;
    const long = Math.max(tw, th);
    const w = tw / long;
    const h = th / long;
    const segsX = 60;
    const segsY = Math.max(24, Math.round(60 * (h / w)));

    const mesh = MeshBuilder.CreateGround('cloth', { width: w, height: h, subdivisions: Math.max(segsX, segsY), updatable: true }, scene);
    mesh.rotation.x = -Math.PI / 2; // stand it up, design facing the camera
    mesh.position.y = h / 2 - 0.44;
    const mat = designMaterial(scene, tex);
    mat.backFaceCulling = false;
    mesh.material = mat;

    const base = mesh.getVerticesData(VertexBuffer.PositionKind).slice();
    let t = 0;
    scene.onBeforeRenderObservable.add(() => {
        t += scene.getEngine().getDeltaTime() / 1000;
        const pos = mesh.getVerticesData(VertexBuffer.PositionKind);
        for (let i = 0; i < pos.length; i += 3) {
            const x = base[i];
            const z = base[i + 2];
            // gentle two-wave ripple, calmer near the top edge (hung banner);
            // z=+h/2 is the top once the plane stands up
            const hang = 0.35 + 0.65 * (0.5 - z / h);
            pos[i + 1] = 0.022 * hang * Math.sin(6.0 * x + t * 1.6) + 0.012 * hang * Math.sin(9.0 * (x + z) + t * 2.3);
        }
        mesh.updateVerticesData(VertexBuffer.PositionKind, pos);
    });
    mesh.refreshBoundingInfo();

    return [mesh];
}

/** Mug: ceramic cylinder + handle, the design wrapped around the side. */
function mug(scene, spec, tex) {
    const root = new TransformNode('mug', scene);
    const body = MeshBuilder.CreateCylinder('mug-body', { height: 0.85, diameter: 0.8, tessellation: 64 }, scene);
    body.material = bodyMaterial(scene, '#fbfaf7');
    body.parent = root;

    // print band: a slightly wider open cylinder carrying the design
    const band = MeshBuilder.CreateCylinder('mug-band', { height: 0.58, diameter: 0.808, tessellation: 64, cap: Mesh.NO_CAP }, scene);
    const bandMat = designMaterial(scene, tex);
    bandMat.backFaceCulling = true;
    if (tex) {
        tex.wrapU = Texture.CLAMP_ADDRESSMODE;
        // wrap the design around the front of the mug; cylinder UVs run the
        // other way, so mirror U to keep the design readable
        tex.uScale = -1.6;
        tex.uOffset = 1.3;
    }
    band.material = bandMat;
    band.parent = root;

    // vertical loop beside the body, swung toward the camera so it reads
    const handlePivot = new TransformNode('mug-handle-pivot', scene);
    handlePivot.parent = root;
    handlePivot.rotation.y = 0.9;
    const handle = MeshBuilder.CreateTorus('mug-handle', { diameter: 0.42, thickness: 0.08, tessellation: 40 }, scene);
    handle.rotation.x = Math.PI / 2;
    handle.position.x = 0.44;
    handle.material = bodyMaterial(scene, '#fbfaf7');
    handle.parent = handlePivot;

    const inside = MeshBuilder.CreateCylinder('mug-inside', { height: 0.02, diameter: 0.72, tessellation: 64 }, scene);
    inside.position.y = 0.42;
    inside.material = bodyMaterial(scene, '#e8e4da');
    inside.parent = root;

    root.position.y = -0.06;
    return [body, band, handle];
}

/** Tote bag: canvas slab + two handles, the design printed on the front face. */
function tote(scene, spec, tex) {
    const root = new TransformNode('tote', scene);
    const w = 0.85;
    const h = 0.8;
    const d = 0.09;
    const r = 4; // soft body corners, in the 0–100 path space

    // built flat in XZ like the slab, then the whole group stands up
    const rect = samplePath(`M ${r} 0 L ${100 - r} 0 A ${r} ${r} 0 0 1 100 ${r} L 100 100 L 0 100 L 0 ${r} A ${r} ${r} 0 0 1 ${r} 0 Z`)[0];
    const toXZ = (p) => new Vector3((p.x / 100 - 0.5) * w, 0, (0.5 - p.y / 100) * h);
    const outline = rect.map(toXZ);

    const body = MeshBuilder.ExtrudePolygon('tote-body', { shape: outline, depth: d }, scene, earcut);
    body.material = bodyMaterial(scene, '#efe9db'); // natural canvas
    body.position.y = d;
    body.parent = root;

    const face = MeshBuilder.CreatePolygon('tote-face', { shape: outline }, scene, earcut);
    remapTopUVs(face, w, h);
    face.material = designMaterial(scene, tex);
    face.position.y = d + 0.0015;
    face.parent = root;

    for (const s of [-1, 1]) {
        const handle = MeshBuilder.CreateTorus(`tote-handle${s}`, { diameter: 0.4, thickness: 0.035, tessellation: 48 }, scene);
        // flat in group space (XZ) like everything else — vertical once the group stands
        handle.position.set(s * 0.19, d / 2, h / 2 - 0.02);
        handle.material = bodyMaterial(scene, '#dfd7c4');
        handle.parent = root;
    }

    root.rotation.x = -Math.PI / 2 + 0.06;
    root.position.y = h / 2 - 0.42;
    return root.getChildMeshes();
}

/** Tee/hoodie/polo: extruded shirt silhouette, the design printed on the chest. */
function shirt(scene, spec, tex) {
    const root = new TransformNode('shirt', scene);
    // t-shirt outline in the same 0–100 (y down) space the die cuts use
    const outlinePath = 'M 30 6 L 41 2 Q 50 9 59 2 L 70 6 L 92 18 L 84 36 L 72 30 L 72 88 L 28 88 L 28 30 L 16 36 L 8 18 Z';
    const rings = samplePath(outlinePath);
    const toXZ = (p) => new Vector3((p.x / 100 - 0.5) * 0.98, 0, (0.5 - p.y / 100) * 0.98);
    const outline = rings[0].map(toXZ);

    const body = MeshBuilder.ExtrudePolygon('shirt-body', { shape: outline, depth: 0.05 }, scene, earcut);
    body.material = bodyMaterial(scene, '#eef0f2');
    body.position.y = 0.05;
    body.parent = root;

    // chest print — same flat-polygon technique as the slab face, so the UV
    // mapping and orientation are identical (no decal guesswork)
    const pw = 0.4;
    const ph = Math.min(0.42, pw * ((Number(spec.trimH) || 1) / (Number(spec.trimW) || 1)));
    const printShape = [
        new Vector3(-pw / 2, 0, -ph / 2), new Vector3(pw / 2, 0, -ph / 2),
        new Vector3(pw / 2, 0, ph / 2), new Vector3(-pw / 2, 0, ph / 2),
    ];
    const print = MeshBuilder.CreatePolygon('shirt-print', { shape: printShape }, scene, earcut);
    remapTopUVs(print, pw, ph);
    print.material = designMaterial(scene, tex);
    print.position.set(0, 0.052, 0.1); // chest height in the flat layout
    print.parent = root;

    root.rotation.x = -Math.PI / 2 + 0.08;
    root.position.y = 0.98 / 2 - 0.46;
    return [body, print];
}

const BUILDERS = { slab, cloth, mug, tote, shirt };
