"""Build the Review-step 3D preview models (mug, tshirt, tote) as .glb.

    blender --background --python backend/scripts/blender/gen_models.py

Mug and tee start from Poly Pizza downloads in ./source (CC-BY 3.0 — see
CREDITS.md); the tote is modelled here. Every model is normalised to ~1 unit,
centred, re-materialed, and given a mesh named  Print  whose vertices are
RAY-CAST onto the product surface (so the design hugs the real geometry) with
clean 0–1 UVs. The Babylon loader (resources/js/lib/preview3d.js) textures
whatever mesh is named Print and re-lights the rest.

Blender is Z-up; +Y is the SCREEN FRONT and -X is page-right once the glb
travels the glTF→Babylon chain (calibrated with a colour-compass model).
"""

import glob
import math
import os

import bmesh
import bpy
from mathutils import Vector

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, 'source')
OUT = os.path.abspath(os.path.join(HERE, '..', '..', 'public', 'models'))

MUG_SRC = 'dec20800-65b1-4a52-b3fb-3ce03243bf29.glb'      # "Mug" — Poly by Google, CC-BY 3.0
TEE_SRC = '859b2ce6-83a6-41bc-a7da-8f2ddba25fff.glb'      # "T-shirt" — Poly by Google, CC-BY 3.0


# --------------------------------------------------------------------------- helpers

def reset():
    bpy.ops.wm.read_factory_settings(use_empty=True)


def activate(obj):
    bpy.ops.object.select_all(action='DESELECT')
    obj.select_set(True)
    bpy.context.view_layer.objects.active = obj


def material(name, rgba, roughness=0.7):
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    b = m.node_tree.nodes['Principled BSDF']
    b.inputs['Base Color'].default_value = rgba
    b.inputs['Roughness'].default_value = roughness
    return m


def shade_smooth(obj, angle=40):
    activate(obj)
    bpy.ops.object.shade_smooth()
    if hasattr(obj.data, 'use_auto_smooth'):
        obj.data.use_auto_smooth = True
        obj.data.auto_smooth_angle = math.radians(angle)


def import_join(path):
    """Import a glb and join all its meshes into one object with WORLD
    transforms baked — glTF hierarchies often rotate/scale via parent empties,
    which transform_apply alone would lose."""
    before = set(bpy.context.scene.objects)
    bpy.ops.import_scene.gltf(filepath=path)
    meshes = [o for o in set(bpy.context.scene.objects) - before if o.type == 'MESH']
    activate(meshes[0])
    for m in meshes:
        m.select_set(True)
    bpy.ops.object.parent_clear(type='CLEAR_KEEP_TRANSFORM')
    bpy.ops.object.join()
    obj = bpy.context.view_layer.objects.active
    bpy.ops.object.transform_apply(location=True, rotation=True, scale=True)
    # drop leftover empties from the glTF hierarchy
    for o in list(set(bpy.context.scene.objects) - before):
        if o.type != 'MESH' and o is not obj:
            bpy.data.objects.remove(o, do_unlink=True)
    return obj


def bbox(obj):
    pts = [obj.matrix_world @ Vector(c) for c in obj.bound_box]
    mins = Vector((min(p[i] for p in pts) for i in range(3)))
    maxs = Vector((max(p[i] for p in pts) for i in range(3)))
    return mins, maxs


def normalize(obj, height=1.0, yaw_deg=0.0):
    """Uniform-scale to `height` (Z), rotate around Z, centre on the origin."""
    activate(obj)
    obj.rotation_euler = (0, 0, math.radians(yaw_deg))
    bpy.ops.object.transform_apply(rotation=True)
    mins, maxs = bbox(obj)
    s = height / max(maxs.z - mins.z, 1e-6)
    obj.scale = (s, s, s)
    bpy.ops.object.transform_apply(scale=True)
    mins, maxs = bbox(obj)
    obj.location = -(mins + maxs) / 2
    bpy.ops.object.transform_apply(location=True)
    return obj


def strip_materials(obj, mat):
    obj.data.materials.clear()
    obj.data.materials.append(mat)


def fix_normals(obj):
    activate(obj)
    bpy.ops.object.mode_set(mode='EDIT')
    bpy.ops.mesh.select_all(action='SELECT')
    bpy.ops.mesh.normals_make_consistent(inside=False)
    bpy.ops.object.mode_set(mode='OBJECT')


def grid_uvs(obj):
    """0–1 UVs from the XZ extent, authored 180°-rotated: the glTF exporter
    flips the V origin and the handedness flip mirrors X, so plain (x, z)
    mapping would show the design upside-down and mirrored on screen."""
    me = obj.data
    if not me.uv_layers:
        me.uv_layers.new(name='UVMap')
    uv = me.uv_layers.active.data
    xs = [v.co.x for v in me.vertices]
    zs = [v.co.z for v in me.vertices]
    x0, x1, z0, z1 = min(xs), max(xs), min(zs), max(zs)
    for loop in me.loops:
        co = me.vertices[loop.vertex_index].co
        uv[loop.index].uv = ((x1 - co.x) / (x1 - x0 or 1), (z1 - co.z) / (z1 - z0 or 1))


def conforming_patch(target, name, width, height, center_z, offset=0.01, res=28):
    """A grid whose vertices are ray-cast (from +Y, the screen-facing side)
    onto `target`, then pulled `offset` toward the camera — the print hugs
    the real surface."""
    bpy.ops.mesh.primitive_grid_add(x_subdivisions=res, y_subdivisions=res, size=1)
    patch = bpy.context.active_object
    patch.name = name
    patch.scale = (width / 2, height / 2, 1)
    bpy.ops.object.transform_apply(scale=True)
    patch.rotation_euler = (math.radians(90), 0, 0)
    bpy.ops.object.transform_apply(rotation=True)

    mins, maxs = bbox(target)
    start_y = maxs.y + 0.5
    for v in patch.data.vertices:
        origin = Vector((v.co.x, start_y, v.co.z + center_z))
        hit, loc, _n, _i = target.ray_cast(origin, Vector((0, -1, 0)))
        v.co.y = (loc.y if hit else 0.0) + offset
        v.co.z += center_z
    grid_uvs(patch)
    patch.data.materials.append(material('Print', (1, 1, 1, 1), roughness=0.75))
    shade_smooth(patch, 80)
    return patch


def handle_azimuth(body, z_lim=0.25):
    """Mean azimuth (about +Y, the screen front) of radial outliers = the handle."""
    mid = [(v.co.x, v.co.y) for v in body.data.vertices if abs(v.co.z) < z_lim]
    rs = sorted(math.hypot(x, y) for x, y in mid)
    r_med = rs[len(rs) // 2]
    out = [(x, y) for x, y in mid if math.hypot(x, y) > 1.3 * r_med]
    if not out:
        return None
    mx = sum(p[0] for p in out) / len(out)
    my = sum(p[1] for p in out) / len(out)
    return math.atan2(mx, my)


def conforming_band(target, name, z_half=0.23, span_deg=210, offset=0.006, res_a=72, res_z=12):
    """A cylindrical band ray-cast RADIALLY onto `target` — every vertex sits
    `offset` outside the real barrel wall, so no centre/radius guessing. The
    arc is centred on +Y (screen front); UVs mirror u for the glTF flip."""
    span = math.radians(span_deg)
    bm = bmesh.new()
    grid = []
    for j in range(res_z + 1):
        z = -z_half + 2 * z_half * j / res_z
        row = []
        for i in range(res_a + 1):
            a = -span / 2 + span * i / res_a
            dx, dy = math.sin(a), math.cos(a)
            hit, loc, _n, _f = target.ray_cast(Vector((dx * 2, dy * 2, z)), Vector((-dx, -dy, 0)))
            r = math.hypot(loc.x, loc.y) if hit else 0.3
            row.append(bm.verts.new((dx * (r + offset), dy * (r + offset), z)))
        grid.append(row)
    for j in range(res_z):
        for i in range(res_a):
            bm.faces.new((grid[j][i], grid[j][i + 1], grid[j + 1][i + 1], grid[j + 1][i]))
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    # recalc on an open shell can pick either side — force outward (radial)
    sample = bm.faces[:] and bm.faces[0]
    if sample:
        c = sample.calc_center_median()
        if sample.normal.dot(Vector((c.x, c.y, 0))) < 0:
            bmesh.ops.reverse_faces(bm, faces=bm.faces)
    me = bpy.data.meshes.new(name)
    bm.to_mesh(me)
    bm.free()
    band = bpy.data.objects.new(name, me)
    bpy.context.collection.objects.link(band)

    me.uv_layers.new(name='UVMap')
    uv = me.uv_layers.active.data
    for loop in me.loops:
        co = me.vertices[loop.vertex_index].co
        a = math.atan2(co.x, co.y)
        uv[loop.index].uv = (0.5 - a / span, (z_half - co.z) / (2 * z_half))
    band.data.materials.append(material('Print', (1, 1, 1, 1), roughness=0.6))
    shade_smooth(band, 60)
    return band


# The whole finished assembly spins by this before export: the compass model
# showed the +Y-authored front rendering ~45° right of the initial camera.
FRONT_YAW = math.radians(-45)


def export(name):
    os.makedirs(OUT, exist_ok=True)
    from mathutils import Matrix
    spin = Matrix.Rotation(FRONT_YAW, 4, 'Z')
    for o in bpy.context.scene.objects:
        if o.type == 'MESH':
            o.matrix_world = spin @ o.matrix_world   # about the WORLD origin
    path = os.path.join(OUT, f'{name}.glb')
    bpy.ops.export_scene.gltf(filepath=path, export_format='GLB', export_apply=True, export_yup=True)
    print(f'wrote {path}')


# --------------------------------------------------------------------------- mug

def build_mug():
    reset()
    body = import_join(os.path.join(SRC, MUG_SRC))
    body = normalize(body, height=0.8, yaw_deg=0)
    # measure where the handle points, then spin it out of the print arc and
    # to the page's back-right (page-right = -X, page-back = -Y ⇒ az -135°)
    az = handle_azimuth(body)
    if az is not None:
        body = normalize(body, height=0.8, yaw_deg=math.degrees(math.radians(135) - az))
    fix_normals(body)
    strip_materials(body, material('Ceramic', (0.97, 0.96, 0.94, 1), roughness=0.3))
    shade_smooth(body, 50)

    conforming_band(body, 'Print', z_half=0.23, span_deg=210, offset=0.006)
    export('mug')


# --------------------------------------------------------------------------- tee

def build_tshirt():
    reset()
    tee = import_join(os.path.join(SRC, TEE_SRC))
    # the source tee natively faces +Y — exactly the screen front
    tee = normalize(tee, height=1.0, yaw_deg=0)
    strip_materials(tee, material('Fabric', (0.92, 0.93, 0.95, 1), roughness=0.9))
    shade_smooth(tee, 55)

    conforming_patch(tee, 'Print', width=0.34, height=0.30, center_z=0.06, offset=0.012)
    export('tshirt')


# --------------------------------------------------------------------------- tote

def build_tote():
    reset()
    bpy.ops.mesh.primitive_cube_add(size=1)
    body = bpy.context.active_object
    body.name = 'ToteBody'
    body.scale = (0.42, 0.07, 0.36)
    bpy.ops.object.transform_apply(scale=True)
    bev = body.modifiers.new('bevel', 'BEVEL')
    bev.width = 0.028
    bev.segments = 4
    bev.use_clamp_overlap = True
    sub = body.modifiers.new('subsurf', 'SUBSURF')
    sub.levels = 2
    activate(body)
    for mod in list(body.modifiers):
        bpy.ops.object.modifier_apply(modifier=mod.name)
    for v in body.data.vertices:
        x, y, z = v.co
        v.co.y *= 1 + 2.2 * max(0.0, -z)         # belly toward the bottom
        v.co.x *= 1 + 0.06 * (z + 0.36)          # slightly wider top
    body.data.materials.append(material('CanvasBody', (0.91, 0.87, 0.77, 1), roughness=0.95))
    shade_smooth(body, 55)

    # handles: torus rings standing in the XZ plane (facing the camera),
    # bottoms sunk into the measured body top so nothing floats
    mins, maxs = bbox(body)
    for sx in (-0.17, 0.17):
        bpy.ops.mesh.primitive_torus_add(major_radius=0.155, minor_radius=0.02,
                                         major_segments=48, minor_segments=12,
                                         location=(sx, 0, maxs.z + 0.055),
                                         rotation=(math.radians(90), 0, 0))
        strap = bpy.context.active_object
        strap.name = 'Strap'
        strap.data.materials.append(material('Strap', (0.80, 0.75, 0.62, 1), roughness=0.95))
        shade_smooth(strap)

    conforming_patch(body, 'Print', width=0.56, height=0.44, center_z=-0.04, offset=0.012)
    export('tote')


# ---------------------------------------------------------------------------

if __name__ == '__main__':
    build_mug()
    build_tshirt()
    build_tote()
    print('ALL MODELS DONE')
