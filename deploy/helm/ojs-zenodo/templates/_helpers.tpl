{{- define "ojs-zenodo.name" -}}
{{- .Release.Name | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{- define "ojs-zenodo.labels" -}}
app.kubernetes.io/name: {{ include "ojs-zenodo.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
app.kubernetes.io/managed-by: Helm
{{- end -}}
