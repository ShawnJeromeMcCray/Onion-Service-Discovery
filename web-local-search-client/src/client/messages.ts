/** Plain-language explanations for typed codes: what happened, what still works, what to do. */
const sourceMessages:{[code:string]:string}={
  SOURCE_FETCH_FAILED:'The Node did not answer. It may be offline or unreachable over Tor right now; try again later. The last verified local index stays available.',
  SOURCE_HTTP_STATUS_REJECTED:'The Node answered with an error page instead of signed data. Nothing from it was used; try again later.',
  SOURCE_TIMEOUT:'The Node answered too slowly. Nothing from this attempt was used; try again later.',
  SOURCE_TIME_BUDGET_EXHAUSTED:'Downloading this Node’s data took more than 90 seconds in total, so nothing partial was used. Try again later; the Node or the Tor connection may be slow.',
  SOURCE_WORK_BUDGET_EXHAUSTED:'This Node offered more data than this page checks at once. Nothing from it was used.',
  RESPONSE_TOO_LARGE:'This Node offered more data than this page checks at once. Nothing from it was used.',
  SOURCE_CHECKPOINT_EQUIVOCATION:'This Node signed two different states for the same moment. Its data is set aside and both signatures are kept as evidence.',
  SOURCE_DESCRIPTOR_EQUIVOCATION:'This Node signed two different descriptions of itself. Its data is set aside and both signatures are kept as evidence.',
  SOURCE_CHECKPOINT_ROLLBACK:'This Node served an older state than it showed before. Its data is set aside.',
  SOURCE_DESCRIPTOR_ROLLBACK_OR_FORK:'This Node served an older or conflicting description of itself. Its data is set aside.',
  SOURCE_CHECKPOINT_CHAIN_BROKEN:'This Node’s states do not connect to the one it showed before. Its data is set aside.',
  SOURCE_NODE_KEY_CHANGED:'This Node signed with a different key than expected. Check the key you entered; if it is right, another service may be answering at this address.',
  SOURCE_NODE_BINDING_MISMATCH:'The data does not belong to the Node at this address. Nothing from it was used.',
  SOURCE_DESCRIPTOR_HISTORY_INCOMPLETE:'This Node’s current description does not connect to the one this browser saw earlier, so nothing from it was used. Loading again helps only once the Node serves the missing history.',
  SOURCE_NOT_IN_HOST_ALLOWLIST:'This Node is not listed by this Client Host and was not added by you.',
};
const operationMessages:{[code:string]:string}={
  CLIENT_BUSY:'Another load is still running. Wait for it or cancel it first.',
  PIN_CONTEXT_UNAVAILABLE:'This identity cannot be pinned right now: it has no current published Manifest in the loaded data, or you already have 256 pins.',
  PIN_BUDGET_EXHAUSTED:'You can keep at most 256 pins. Remove some before importing more.',
  PIN_BACKUP_INVALID:'This file is not a valid pin backup from this page.',
  IMPORT_FORMAT_INVALID:'This file is not a signed record bundle.',
  CLIENT_RECORD_BUDGET_EXHAUSTED:'The local index is full (10,000 signed records). Clear the index before loading more.',
  ENCODING_INVALID:'The file is not valid UTF-8 JSON.',
  JSON_INVALID:'The file is not valid JSON.',
  DOCUMENT_TOO_LARGE:'The file is larger than this page accepts.',
  SIGNATURE_INVALID:'A record’s signature does not verify. Nothing from the file was used.',
  SCHEMA_INVALID:'A record does not have the required form. Nothing from the file was used.',
};
export function sourceMessage(code:string):string { return sourceMessages[code]??(/^(SIGNATURE|SCHEMA|JSON|ENCODING|HASH|CONTENT|HEADERS)_/.test(code)?'This Node sent data that failed verification. Nothing from it was used.':'Loading this Node failed. The last verified local index stays available.'); }
export function operationMessage(code:string):string { return operationMessages[code]??'The operation stopped. Existing pins and evidence are unchanged.'; }
