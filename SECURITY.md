## Supported Versions

Only the latest release gets security fixes. To get a fix, update to the latest release on the
[releases page](https://github.com/MahoCommerce/maho/releases).

If you need more support, consider [becoming a sponsor](https://mahocommerce.com/sponsors/).

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

## Limitation of Liability

Maho is available under the [OSL 3.0](https://opensource.org/licenses/OSL-3.0) and
[AFL 3.0](https://opensource.org/licenses/AFL-3.0) licenses. Section 8 of both licenses says
that you use this source code at your own risk:

> 8. Limitation of Liability. Under no circumstances and under no legal theory, whether in tort (including negligence), contract, or otherwise, shall the Licensor be liable to anyone for any indirect, special, incidental, or consequential damages of any character arising as a result of this License or the use of the Original Work including, without limitation, damages for loss of goodwill, work stoppage, computer failure or malfunction, or any and all other commercial damages or losses. This limitation of liability shall not apply to the extent applicable law prohibits such limitation.

For the full text, see `LICENSE.txt` and `LICENSE_AFL.txt`.
