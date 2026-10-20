## Reporting Security Issues

If you believe you have found a security vulnerability in Maho, please report it through coordinated disclosure.

**Please do not report security vulnerabilities through the repository issues, discussions, or pull requests**.
Instead, please [open a new Github security advisory](https://github.com/MahoCommerce/maho/security/advisories/new).

Please include the following information to help us better understand and resolve the issue:

- Type of vulnerability (e.g., buffer overflow, SQL injection, cross-site scripting)
- Full paths of affected source file(s)
- Location of the affected source code (tag/branch/commit or direct URL)
- Required configuration to reproduce the issue
- Step-by-step reproduction instructions
- Proof-of-concept or exploit code (if possible)
- Impact assessment, including potential attack vectors
- Any proposed mitigations or fixes (optional)

## Verifying a Release

Each release from 26.11.0 has two assets: `maho-<version>.zip` and its CycloneDX SBOM,
`maho-<version>.cdx.json`. A reusable workflow in
[MahoCommerce/infrastructure](https://github.com/MahoCommerce/infrastructure) builds them on a
GitHub-hosted runner and signs the build provenance (SLSA v1) and the SBOM with GitHub artifact
attestations. The build steps cannot reach the signing identity, and a change in this repository
cannot change the build (SLSA Build Level 3).

Check the provenance of a downloaded package:

```bash
gh attestation verify maho-26.11.0.zip --repo MahoCommerce/maho \
  --signer-workflow MahoCommerce/infrastructure/.github/workflows/release-build.yml \
  --source-ref refs/heads/main
```

For a patch release from a maintenance branch, use that branch in `--source-ref`, for example
`refs/heads/26.11`. Check the SBOM attestation with `--predicate-type https://cyclonedx.org/bom`.

Releases are immutable. Check that the release and its assets are the ones GitHub published:

```bash
gh release verify 26.11.0 --repo MahoCommerce/maho
gh release verify-asset 26.11.0 maho-26.11.0.zip --repo MahoCommerce/maho
```

Composer installs the source archive that GitHub generates for the tag, not this package.
