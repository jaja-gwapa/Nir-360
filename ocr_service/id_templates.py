"""
ID template definitions for OpenCV region cropping.
Each template defines relative regions (0-1) for name, birthdate, address.
Coordinates are (x, y, width, height) as fraction of image dimensions.
Add new ID types (e.g. driver's license, other national IDs) here.
"""

# -----------------------------------------------------------------------------
# PhilID / Philippine National ID (typical layout: photo left, text right)
# Regions tuned for standard card; scale to actual image size in crop_regions().
# -----------------------------------------------------------------------------
TEMPLATE_PHILID = {
    "name": (0.28, 0.14, 0.70, 0.24),      # Apelyido, Given, Middle
    "birthdate": (0.28, 0.38, 0.70, 0.16), # Petsa ng Kapanganakan (e.g. JUNE 17, 2002)
    "address": (0.28, 0.52, 0.70, 0.38),   # Tirahan / address block
}

# Generic government ID (wider text area; adjust if your IDs differ)
TEMPLATE_GENERIC = {
    "name": (0.25, 0.15, 0.72, 0.22),
    "birthdate": (0.25, 0.40, 0.72, 0.14),
    "address": (0.25, 0.56, 0.72, 0.35),
}

# Default template used when no type is specified
DEFAULT_TEMPLATE = TEMPLATE_PHILID

# Optional: map template names to configs for future /scan-id?template=philid
TEMPLATES = {
    "philid": TEMPLATE_PHILID,
    "generic": TEMPLATE_GENERIC,
}
