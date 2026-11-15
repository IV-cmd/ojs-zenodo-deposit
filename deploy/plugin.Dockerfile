# Packages the plugin source as the init-container image the Helm chart
# copies into OJS's plugins/generic/zenodoDeposit.
#   docker build -f deploy/plugin.Dockerfile -t <registry>/ojs-zenodo-deposit-plugin:tag .
FROM alpine:3.20
WORKDIR /plugin
COPY ZenodoDepositPlugin.php version.xml ./
COPY classes templates locale vendor ./
