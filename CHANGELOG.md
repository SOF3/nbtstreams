# Changelog

## [Unreleased] - 0.1.0
### Added
- Resolve size and type with pass-by-ref args in `NbtReader::startList()`
- `NbtReader::peekInt()` for sizes of lists and arrays

### Changed
- `NbtReader::readByte()` now defaults to reading signed bytes as per Minecraft Wiki specification

### Fixed
- Fixed missing return statement in `NbtReader::name()`

## 0.0.0 - 2017/08/02
Initial development release

[Unreleased]: https://github.com/SOF3/nbtstreams/compare/v0.0.0...HEAD
