# pxc-operator: add a tenant to `watchNamespace`

**Every new tenant needs this step.** The Percona operator is namespace-scoped, so
a `PerconaXtraDBCluster` in a namespace it does not watch is simply ignored.

The failure is silent, which is what makes it worth documenting: `helm install`
reports success, the CR is created, and then nothing happens — no pods, no
events, no error. It looks like a cluster that is still starting.

## The change

Release `pxc-operator`, chart `pxc-operator-1.20.0`, namespace `mysql-operator`
(currently revision 5). One value changes:

```diff
 watchAllNamespaces: false
-watchNamespace: jo-moodle-prod,sharda-moodle-prod,learn-usdc-prod,global-jain-prod,sharda-moodle-recovery,jo-moodle-recovery
+watchNamespace: jo-moodle-prod,sharda-moodle-prod,learn-usdc-prod,global-jain-prod,sharda-moodle-recovery,jo-moodle-recovery,nitk-prod
```

Nothing else. RBAC is **cluster-scoped** — there is a `pxc-operator` ClusterRole
and ClusterRoleBinding, and no per-namespace Roles exist in any watched namespace
(checked `jo-moodle-prod` and `sharda-moodle-prod`: zero pxc Roles or
RoleBindings). So adding the namespace to the list is sufficient; the operator
already has permission to act there.

## Where this value actually lives

**Source of truth:** `charts/mysql-operator/helmfile.yaml` in the
`kai/infra/kube-deployments` repository.

    https://coderepos.thekaios.com/kai/infra/kube-deployments
      -> charts/mysql-operator/helmfile.yaml

Change `watchNamespace` in that file and let helmfile reconcile. Raise it as an
MR there — that repo owns the operator, not this one.

## Do not change it any other way

Neither of these survives:

- **`kubectl edit` on the Deployment** — reverted by the next helmfile or helm
  run.
- **`helm upgrade` with `helm get values`** — also reverted, because helmfile
  re-applies from the file, not from what is currently deployed. It additionally
  risks resolving a newer chart unless `--version 1.20.0` is pinned, which would
  turn a one-line config change into an operator upgrade affecting **five live
  production databases** (jain, sharda, learn-usdc, global-jain, and the
  recovery namespaces).

Either way the operator would eventually stop reconciling the new tenant's
database — including its backups — with no error surfaced anywhere. Change it in
the helmfile.

## Verify

```sh
kubectl -n mysql-operator get deploy pxc-operator \
  -o jsonpath='{.spec.template.spec.containers[0].env[?(@.name=="WATCH_NAMESPACE")].value}'
```

`nitk-prod` should appear, and the operator pod should have restarted (the env
change rolls it). Existing clusters are untouched by the restart — the operator
is stateless and reconciles from the CRs.

## Then, and only then

```sh
helm repo add percona https://percona.github.io/percona-helm-charts/
helm install mysql-db percona/pxc-db -n nitk-prod \
  --version 1.20.0 -f deploy/nitk-pxc-values.yaml
```

Confirm it is actually reconciling rather than sitting inert:

```sh
kubectl -n nitk-prod get pxc          # should progress to ready
kubectl -n nitk-prod get pods -w      # pxc + haproxy pods should appear
```

If `kubectl get pxc` shows the CR but no pods appear within a couple of minutes,
the watch list did not take effect.

## Prerequisite for the backup config

`deploy/nitk-pxc-values.yaml` references a Secret `nitk-moodle-s3-credentials`
for its S3 backups. Create that in `nitk-prod` first, or the backup section will
not reconcile.
