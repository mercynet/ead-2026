# Product feedback intake

Não é um clone de Jira. Uma linha por evidência relevante:

```yaml
customer: ""
tenant_reference: ""
date: "YYYY-MM-DD"
category: "BUG|OPERATIONS|UX|BLOCKING_CAPABILITY|FEATURE_REQUEST|INTEGRATION|COMMERCIAL"
severity: "P1|P2|P3|P4"
frequency: "once|recurring|every-user|unknown"
revenue_impact: "none|possible|renewal-risk|expansion-opportunity"
deal_blocker: false
workaround: ""
evidence: "request-id / screenshot-reference / interview-note"
recommended_disposition: "fix-now|operate-manually|measure|roadmap|decline|legal-review"
owner: ""
```

PII e secrets não entram no intake; usar referências seguras. Um BUG reproduzível não deve ser
rebaixado a feature request. Um pedido que impede o resultado vendido deve ser
`BLOCKING_CAPABILITY`, mesmo que seja tecnicamente uma feature nova.
