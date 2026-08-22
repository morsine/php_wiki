# HPE ProLiant DL380 Gen9

Notes on the memory layout and IML error handling for this chassis.

## Symptoms

- IML POST Error 207
- Invalid DIMM slot population across both processors

## Resolution

Follow the four-channel population rules per processor and rearrange DIMMs
so each channel is populated symmetrically before re-seating.
