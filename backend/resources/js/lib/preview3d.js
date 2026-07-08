// 3D product preview for the Review step (Babylon.js).
//
// PROCEDURAL ONLY — every mesh is generated in code from the product's surface
// spec, no downloaded/authored assets. Flat print (cards, flyers, postcards,
// door hangers, labels, stickers, posters) becomes a thin slab in its exact die
// shape (holes included: the door-hanger hook); banners/tablecloths become a
// gently waving cloth. Complex 3D products (mugs, apparel, totes) do NOT get a
// 3D preview — they fall back to the flat design image (detectKind returns null
// for them), because a convincing mug/shirt needs a real modelled/licensed
// asset and low-poly stand-ins read as cheap.
//
//   const preview = await mountPreview3D(canvasEl, { kind, spec, texture });
//   preview.dispose();
//
// kind: 'slab' | 'cloth'   (detectKind → one of these, or null = no 3D)
// spec:  the PrintSpec canvas payload — { trimW, trimH, cut, ... }
// texture: URL or data-URL of the design preview (same-origin / data)

import {
    ArcRotateCamera, Color3, Color4, DirectionalLight, Engine, HemisphericLight,
    MeshBuilder, Scene, SceneLoader, ShadowGenerator, StandardMaterial, Texture,
    TransformNode, Vector3, VertexBuffer,
} from '@babylonjs/core';
import '@babylonjs/core/Loading/Plugins/babylonFileLoader'; // registers the native .babylon loader
import earcut from 'earcut';

/**
 * Which preview fits this product:
 *  - 'mug' / 'shirt': a real .babylon model (see MODELS) textured with the design
 *  - 'cloth': procedural waving banner/tablecloth from the surface spec
 *  - 'slab': procedural flat print in its exact die shape (the default)
 *  - null: complex product with no model yet (tote/hat/…) → flat image only
 */
export function detectKind(product = {}, category = {}) {
    const n = `${product.slug || ''} ${product.name || ''}`.toLowerCase();
    if (/\bmug|cup\b|becher|tasse/.test(n)) return 'mug';
    if (/t-?shirt|hoodie|sweatshirt|polo|jersey/.test(n)) return 'shirt';
    if (/tote|\bbag\b|beutel|hat|cap|beanie|pillow|apron|bottle|umbrella|drinkware/.test(n)) {
        return null; // complex product, no model yet — flat image only
    }
    if (/banner|tablecloth|backdrop|table runner|flag/.test(n) && !/frame|stand|pole/.test(n)) return 'cloth';
    return 'slab';
}

export async function mountPreview3D(canvas, { kind = 'slab', spec = {}, texture = null } = {}) {
    // adaptToDeviceRatio renders at the DEVICE's resolution — without it the
    // backing store is CSS pixels and every scaled display (retina, Windows
    // 125%) sees a pixelated upscale
    const engine = new Engine(canvas, true, { preserveDrawingBuffer: true, stencil: false, antialias: true, adaptToDeviceRatio: true });
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
    if (designTex) {
        designTex.anisotropicFilteringLevel = 8; // crisp at glancing angles
        designTex.wrapU = Texture.CLAMP_ADDRESSMODE;
        designTex.wrapV = Texture.CLAMP_ADDRESSMODE;
    }
    const build = BUILDERS[kind] || BUILDERS.slab;
    const cast = await Promise.resolve(build(scene, spec, designTex));
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

/**
 * Real 3D product models (createx-editor .babylon, hosted at /models/createx).
 * `print` = mesh name(s) that carry the printable UV area — the design texture
 * lands there; every other mesh keeps a neutral body colour. `bodyHex` maps a
 * body mesh name to its colour (unlisted body meshes get the default).
 */
const MODELS = {
    mug:   { file: 'mug.babylon',    print: ['texture'], yaw: 0, bodyHex: { color: '#f6f5f2', white: '#f6f5f2' } },
    shirt: { file: 'tshirt.babylon', print: ['polySurface26'], yaw: Math.PI / 2, bodyHex: { 'low:Group1': '#eef0f2' } },
};

async function loadModel(scene, tex, cfg) {
    const res = await SceneLoader.ImportMeshAsync('', '/models/createx/', cfg.file, scene);
    const meshes = res.meshes.filter((m) => (m.getTotalVertices?.() || 0) > 0);
    const print = new Set(cfg.print);
    for (const m of meshes) {
        if (print.has(m.name)) {
            const mat = designMaterial(scene, tex);
            mat.backFaceCulling = false; // print shells are single-sided in the source
            // createx print meshes carry UVs in u[0,1] v[1,2] (V offset by +1) —
            // WRAP addressing folds v:1→2 back onto the design's 0→1
            if (tex) { tex.wrapU = Texture.WRAP_ADDRESSMODE; tex.wrapV = Texture.WRAP_ADDRESSMODE; }
            m.material = mat;
        } else {
            const mat = new StandardMaterial(`${m.name}-body`, scene);
            mat.diffuseColor = Color3.FromHexString(cfg.bodyHex?.[m.name] || '#f0efec');
            mat.specularColor = new Color3(0.05, 0.05, 0.05);
            mat.backFaceCulling = false;
            mat.twoSidedLighting = true;
            m.material = mat;
        }
    }

    // createx models are authored at ~100-unit scale — normalize the whole set
    // to ~1 unit tall, centered on the origin, so the shared camera framing (and
    // the ground/shadows) work identically to the procedural builders.
    const roots = meshes.filter((m) => !m.parent);
    const pivot = new TransformNode('createx-root', scene);
    roots.forEach((m) => { if (m !== pivot) m.parent = pivot; });
    pivot.rotation.y = cfg.yaw || 0; // turn the model's print face toward the camera
    pivot.computeWorldMatrix(true);
    meshes.forEach((m) => m.computeWorldMatrix(true));
    const ext = scene.getWorldExtends((m) => meshes.includes(m));
    const size = ext.max.subtract(ext.min);
    const maxDim = Math.max(size.x, size.y, size.z) || 1;
    const s = 1.0 / maxDim;
    pivot.scaling.setAll(s);
    const center = ext.min.add(size.scale(0.5));
    pivot.position = center.scale(-s);
    pivot.computeWorldMatrix(true);

    return meshes;
}

const BUILDERS = {
    slab,
    cloth,
    mug: (scene, spec, tex) => loadModel(scene, tex, MODELS.mug),
    shirt: (scene, spec, tex) => loadModel(scene, tex, MODELS.shirt),
};
