var __getOwnPropNames = Object.getOwnPropertyNames;
var __commonJS = (cb, mod) => function __require() {
  try {
    return mod || (0, cb[__getOwnPropNames(cb)[0]])((mod = { exports: {} }).exports, mod), mod.exports;
  } catch (e) {
    throw mod = 0, e;
  }
};

// web-local-search-client/node_modules/ajv/dist/runtime/ucs2length.js
var require_ucs2length = __commonJS({
  "web-local-search-client/node_modules/ajv/dist/runtime/ucs2length.js"(exports) {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    function ucs2length(str) {
      const len = str.length;
      let length = 0;
      let pos = 0;
      let value;
      while (pos < len) {
        length++;
        value = str.charCodeAt(pos++);
        if (value >= 55296 && value <= 56319 && pos < len) {
          value = str.charCodeAt(pos);
          if ((value & 64512) === 56320)
            pos++;
        }
      }
      return length;
    }
    exports.default = ucs2length;
    ucs2length.code = 'require("ajv/dist/runtime/ucs2length").default';
  }
});

// web-local-search-client/compiled-schemas.mjs
var direct_observation = validate20;
var schema31 = { "type": "object", "properties": { "protocol": { "const": "Onion Service Discovery" }, "protocol_version": { "const": 1 }, "record_type": { "const": "direct_observation" }, "node_identity_public_key": { "type": "string", "minLength": 51, "maxLength": 51, "pattern": "^ed25519:[A-Za-z0-9_-]{43}$" }, "onion": { "type": "string", "minLength": 62, "maxLength": 62, "pattern": "^[a-z2-7]{56}\\.onion$" }, "service_identity_public_key": { "type": "string", "minLength": 51, "maxLength": 51, "pattern": "^ed25519:[A-Za-z0-9_-]{43}$" }, "revision": { "type": "integer", "minimum": 1, "maximum": 9007199254740991 }, "manifest_hash": { "type": "string", "minLength": 71, "maxLength": 71, "pattern": "^sha256:[0-9a-f]{64}$" }, "result": { "enum": ["SUCCESS_CURRENT", "SUCCESS_UNCHANGED", "SUCCESS_NEW_REVISION", "SUCCESS_VALID_ROTATION", "SUCCESS_VALID_MIGRATION"] }, "observed_at": { "type": "string", "minLength": 20, "maxLength": 20, "pattern": "^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$" }, "signature": { "type": "string", "minLength": 97, "maxLength": 97, "pattern": "^ed25519sig:[A-Za-z0-9_-]{86}$" } }, "required": ["protocol", "protocol_version", "record_type", "node_identity_public_key", "onion", "service_identity_public_key", "revision", "manifest_hash", "result", "observed_at", "signature"], "additionalProperties": false, "$schema": "https://json-schema.org/draft/2020-12/schema", "$id": "https://github.com/ShawnJeromeMcCray/Onion-Service-Discovery/schemas/draft-v1/direct_observation.json", "title": "Onion Service Discovery direct_observation", "$comment": "Implementation draft; protocol freeze and human crypto approval are outstanding." };
var func1 = Object.prototype.hasOwnProperty;
var func2 = require_ucs2length().default;
var pattern4 = new RegExp("^ed25519:[A-Za-z0-9_-]{43}$", "u");
var pattern5 = new RegExp("^[a-z2-7]{56}\\.onion$", "u");
var pattern7 = new RegExp("^sha256:[0-9a-f]{64}$", "u");
var pattern8 = new RegExp("^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$", "u");
var pattern9 = new RegExp("^ed25519sig:[A-Za-z0-9_-]{86}$", "u");
function validate20(data, { instancePath = "", parentData, parentDataProperty, rootData = data, dynamicAnchors = {} } = {}) {
  ;
  let vErrors = null;
  let errors = 0;
  const evaluated0 = validate20.evaluated;
  if (evaluated0.dynamicProps) {
    evaluated0.props = void 0;
  }
  if (evaluated0.dynamicItems) {
    evaluated0.items = void 0;
  }
  if (errors === 0) {
    if (data && typeof data == "object" && !Array.isArray(data)) {
      let missing0;
      if (data.protocol === void 0 && (missing0 = "protocol") || data.protocol_version === void 0 && (missing0 = "protocol_version") || data.record_type === void 0 && (missing0 = "record_type") || data.node_identity_public_key === void 0 && (missing0 = "node_identity_public_key") || data.onion === void 0 && (missing0 = "onion") || data.service_identity_public_key === void 0 && (missing0 = "service_identity_public_key") || data.revision === void 0 && (missing0 = "revision") || data.manifest_hash === void 0 && (missing0 = "manifest_hash") || data.result === void 0 && (missing0 = "result") || data.observed_at === void 0 && (missing0 = "observed_at") || data.signature === void 0 && (missing0 = "signature")) {
        validate20.errors = [{ instancePath, schemaPath: "#/required", keyword: "required", params: { missingProperty: missing0 }, message: "must have required property '" + missing0 + "'" }];
        return false;
      } else {
        const _errs2 = errors;
        for (const key0 in data) {
          if (!func1.call(schema31.properties, key0)) {
            validate20.errors = [{ instancePath, schemaPath: "#/additionalProperties", keyword: "additionalProperties", params: { additionalProperty: key0 }, message: "must NOT have additional properties" }];
            return false;
            break;
          }
        }
        if (_errs2 === errors) {
          if (data.protocol !== void 0) {
            const _errs3 = errors;
            if ("Onion Service Discovery" !== data.protocol) {
              validate20.errors = [{ instancePath: instancePath + "/protocol", schemaPath: "#/properties/protocol/const", keyword: "const", params: { allowedValue: "Onion Service Discovery" }, message: "must be equal to constant" }];
              return false;
            }
            var valid0 = _errs3 === errors;
          } else {
            var valid0 = true;
          }
          if (valid0) {
            if (data.protocol_version !== void 0) {
              const _errs4 = errors;
              if (1 !== data.protocol_version) {
                validate20.errors = [{ instancePath: instancePath + "/protocol_version", schemaPath: "#/properties/protocol_version/const", keyword: "const", params: { allowedValue: 1 }, message: "must be equal to constant" }];
                return false;
              }
              var valid0 = _errs4 === errors;
            } else {
              var valid0 = true;
            }
            if (valid0) {
              if (data.record_type !== void 0) {
                const _errs5 = errors;
                if ("direct_observation" !== data.record_type) {
                  validate20.errors = [{ instancePath: instancePath + "/record_type", schemaPath: "#/properties/record_type/const", keyword: "const", params: { allowedValue: "direct_observation" }, message: "must be equal to constant" }];
                  return false;
                }
                var valid0 = _errs5 === errors;
              } else {
                var valid0 = true;
              }
              if (valid0) {
                if (data.node_identity_public_key !== void 0) {
                  let data3 = data.node_identity_public_key;
                  const _errs6 = errors;
                  if (errors === _errs6) {
                    if (typeof data3 === "string") {
                      if (func2(data3) > 51) {
                        validate20.errors = [{ instancePath: instancePath + "/node_identity_public_key", schemaPath: "#/properties/node_identity_public_key/maxLength", keyword: "maxLength", params: { limit: 51 }, message: "must NOT have more than 51 characters" }];
                        return false;
                      } else {
                        if (func2(data3) < 51) {
                          validate20.errors = [{ instancePath: instancePath + "/node_identity_public_key", schemaPath: "#/properties/node_identity_public_key/minLength", keyword: "minLength", params: { limit: 51 }, message: "must NOT have fewer than 51 characters" }];
                          return false;
                        } else {
                          if (!pattern4.test(data3)) {
                            validate20.errors = [{ instancePath: instancePath + "/node_identity_public_key", schemaPath: "#/properties/node_identity_public_key/pattern", keyword: "pattern", params: { pattern: "^ed25519:[A-Za-z0-9_-]{43}$" }, message: 'must match pattern "^ed25519:[A-Za-z0-9_-]{43}$"' }];
                            return false;
                          }
                        }
                      }
                    } else {
                      validate20.errors = [{ instancePath: instancePath + "/node_identity_public_key", schemaPath: "#/properties/node_identity_public_key/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                      return false;
                    }
                  }
                  var valid0 = _errs6 === errors;
                } else {
                  var valid0 = true;
                }
                if (valid0) {
                  if (data.onion !== void 0) {
                    let data4 = data.onion;
                    const _errs8 = errors;
                    if (errors === _errs8) {
                      if (typeof data4 === "string") {
                        if (func2(data4) > 62) {
                          validate20.errors = [{ instancePath: instancePath + "/onion", schemaPath: "#/properties/onion/maxLength", keyword: "maxLength", params: { limit: 62 }, message: "must NOT have more than 62 characters" }];
                          return false;
                        } else {
                          if (func2(data4) < 62) {
                            validate20.errors = [{ instancePath: instancePath + "/onion", schemaPath: "#/properties/onion/minLength", keyword: "minLength", params: { limit: 62 }, message: "must NOT have fewer than 62 characters" }];
                            return false;
                          } else {
                            if (!pattern5.test(data4)) {
                              validate20.errors = [{ instancePath: instancePath + "/onion", schemaPath: "#/properties/onion/pattern", keyword: "pattern", params: { pattern: "^[a-z2-7]{56}\\.onion$" }, message: 'must match pattern "^[a-z2-7]{56}\\.onion$"' }];
                              return false;
                            }
                          }
                        }
                      } else {
                        validate20.errors = [{ instancePath: instancePath + "/onion", schemaPath: "#/properties/onion/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                        return false;
                      }
                    }
                    var valid0 = _errs8 === errors;
                  } else {
                    var valid0 = true;
                  }
                  if (valid0) {
                    if (data.service_identity_public_key !== void 0) {
                      let data5 = data.service_identity_public_key;
                      const _errs10 = errors;
                      if (errors === _errs10) {
                        if (typeof data5 === "string") {
                          if (func2(data5) > 51) {
                            validate20.errors = [{ instancePath: instancePath + "/service_identity_public_key", schemaPath: "#/properties/service_identity_public_key/maxLength", keyword: "maxLength", params: { limit: 51 }, message: "must NOT have more than 51 characters" }];
                            return false;
                          } else {
                            if (func2(data5) < 51) {
                              validate20.errors = [{ instancePath: instancePath + "/service_identity_public_key", schemaPath: "#/properties/service_identity_public_key/minLength", keyword: "minLength", params: { limit: 51 }, message: "must NOT have fewer than 51 characters" }];
                              return false;
                            } else {
                              if (!pattern4.test(data5)) {
                                validate20.errors = [{ instancePath: instancePath + "/service_identity_public_key", schemaPath: "#/properties/service_identity_public_key/pattern", keyword: "pattern", params: { pattern: "^ed25519:[A-Za-z0-9_-]{43}$" }, message: 'must match pattern "^ed25519:[A-Za-z0-9_-]{43}$"' }];
                                return false;
                              }
                            }
                          }
                        } else {
                          validate20.errors = [{ instancePath: instancePath + "/service_identity_public_key", schemaPath: "#/properties/service_identity_public_key/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                          return false;
                        }
                      }
                      var valid0 = _errs10 === errors;
                    } else {
                      var valid0 = true;
                    }
                    if (valid0) {
                      if (data.revision !== void 0) {
                        let data6 = data.revision;
                        const _errs12 = errors;
                        if (!(typeof data6 == "number" && (!(data6 % 1) && !isNaN(data6)) && isFinite(data6))) {
                          validate20.errors = [{ instancePath: instancePath + "/revision", schemaPath: "#/properties/revision/type", keyword: "type", params: { type: "integer" }, message: "must be integer" }];
                          return false;
                        }
                        if (errors === _errs12) {
                          if (typeof data6 == "number" && isFinite(data6)) {
                            if (data6 > 9007199254740991 || isNaN(data6)) {
                              validate20.errors = [{ instancePath: instancePath + "/revision", schemaPath: "#/properties/revision/maximum", keyword: "maximum", params: { comparison: "<=", limit: 9007199254740991 }, message: "must be <= 9007199254740991" }];
                              return false;
                            } else {
                              if (data6 < 1 || isNaN(data6)) {
                                validate20.errors = [{ instancePath: instancePath + "/revision", schemaPath: "#/properties/revision/minimum", keyword: "minimum", params: { comparison: ">=", limit: 1 }, message: "must be >= 1" }];
                                return false;
                              }
                            }
                          }
                        }
                        var valid0 = _errs12 === errors;
                      } else {
                        var valid0 = true;
                      }
                      if (valid0) {
                        if (data.manifest_hash !== void 0) {
                          let data7 = data.manifest_hash;
                          const _errs14 = errors;
                          if (errors === _errs14) {
                            if (typeof data7 === "string") {
                              if (func2(data7) > 71) {
                                validate20.errors = [{ instancePath: instancePath + "/manifest_hash", schemaPath: "#/properties/manifest_hash/maxLength", keyword: "maxLength", params: { limit: 71 }, message: "must NOT have more than 71 characters" }];
                                return false;
                              } else {
                                if (func2(data7) < 71) {
                                  validate20.errors = [{ instancePath: instancePath + "/manifest_hash", schemaPath: "#/properties/manifest_hash/minLength", keyword: "minLength", params: { limit: 71 }, message: "must NOT have fewer than 71 characters" }];
                                  return false;
                                } else {
                                  if (!pattern7.test(data7)) {
                                    validate20.errors = [{ instancePath: instancePath + "/manifest_hash", schemaPath: "#/properties/manifest_hash/pattern", keyword: "pattern", params: { pattern: "^sha256:[0-9a-f]{64}$" }, message: 'must match pattern "^sha256:[0-9a-f]{64}$"' }];
                                    return false;
                                  }
                                }
                              }
                            } else {
                              validate20.errors = [{ instancePath: instancePath + "/manifest_hash", schemaPath: "#/properties/manifest_hash/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                              return false;
                            }
                          }
                          var valid0 = _errs14 === errors;
                        } else {
                          var valid0 = true;
                        }
                        if (valid0) {
                          if (data.result !== void 0) {
                            let data8 = data.result;
                            const _errs16 = errors;
                            if (!(data8 === "SUCCESS_CURRENT" || data8 === "SUCCESS_UNCHANGED" || data8 === "SUCCESS_NEW_REVISION" || data8 === "SUCCESS_VALID_ROTATION" || data8 === "SUCCESS_VALID_MIGRATION")) {
                              validate20.errors = [{ instancePath: instancePath + "/result", schemaPath: "#/properties/result/enum", keyword: "enum", params: { allowedValues: schema31.properties.result.enum }, message: "must be equal to one of the allowed values" }];
                              return false;
                            }
                            var valid0 = _errs16 === errors;
                          } else {
                            var valid0 = true;
                          }
                          if (valid0) {
                            if (data.observed_at !== void 0) {
                              let data9 = data.observed_at;
                              const _errs17 = errors;
                              if (errors === _errs17) {
                                if (typeof data9 === "string") {
                                  if (func2(data9) > 20) {
                                    validate20.errors = [{ instancePath: instancePath + "/observed_at", schemaPath: "#/properties/observed_at/maxLength", keyword: "maxLength", params: { limit: 20 }, message: "must NOT have more than 20 characters" }];
                                    return false;
                                  } else {
                                    if (func2(data9) < 20) {
                                      validate20.errors = [{ instancePath: instancePath + "/observed_at", schemaPath: "#/properties/observed_at/minLength", keyword: "minLength", params: { limit: 20 }, message: "must NOT have fewer than 20 characters" }];
                                      return false;
                                    } else {
                                      if (!pattern8.test(data9)) {
                                        validate20.errors = [{ instancePath: instancePath + "/observed_at", schemaPath: "#/properties/observed_at/pattern", keyword: "pattern", params: { pattern: "^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$" }, message: 'must match pattern "^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$"' }];
                                        return false;
                                      }
                                    }
                                  }
                                } else {
                                  validate20.errors = [{ instancePath: instancePath + "/observed_at", schemaPath: "#/properties/observed_at/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                  return false;
                                }
                              }
                              var valid0 = _errs17 === errors;
                            } else {
                              var valid0 = true;
                            }
                            if (valid0) {
                              if (data.signature !== void 0) {
                                let data10 = data.signature;
                                const _errs19 = errors;
                                if (errors === _errs19) {
                                  if (typeof data10 === "string") {
                                    if (func2(data10) > 97) {
                                      validate20.errors = [{ instancePath: instancePath + "/signature", schemaPath: "#/properties/signature/maxLength", keyword: "maxLength", params: { limit: 97 }, message: "must NOT have more than 97 characters" }];
                                      return false;
                                    } else {
                                      if (func2(data10) < 97) {
                                        validate20.errors = [{ instancePath: instancePath + "/signature", schemaPath: "#/properties/signature/minLength", keyword: "minLength", params: { limit: 97 }, message: "must NOT have fewer than 97 characters" }];
                                        return false;
                                      } else {
                                        if (!pattern9.test(data10)) {
                                          validate20.errors = [{ instancePath: instancePath + "/signature", schemaPath: "#/properties/signature/pattern", keyword: "pattern", params: { pattern: "^ed25519sig:[A-Za-z0-9_-]{86}$" }, message: 'must match pattern "^ed25519sig:[A-Za-z0-9_-]{86}$"' }];
                                          return false;
                                        }
                                      }
                                    }
                                  } else {
                                    validate20.errors = [{ instancePath: instancePath + "/signature", schemaPath: "#/properties/signature/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                    return false;
                                  }
                                }
                                var valid0 = _errs19 === errors;
                              } else {
                                var valid0 = true;
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    } else {
      validate20.errors = [{ instancePath, schemaPath: "#/type", keyword: "type", params: { type: "object" }, message: "must be object" }];
      return false;
    }
  }
  validate20.errors = vErrors;
  return errors === 0;
}
validate20.evaluated = { "props": true, "dynamicProps": false, "dynamicItems": false };
var node_descriptor = validate21;
var schema32 = { "type": "object", "properties": { "protocol": { "const": "Onion Service Discovery" }, "protocol_version": { "const": 1 }, "record_type": { "const": "node_descriptor" }, "node_identity_public_key": { "type": "string", "minLength": 51, "maxLength": 51, "pattern": "^ed25519:[A-Za-z0-9_-]{43}$" }, "node_onion": { "type": "string", "minLength": 62, "maxLength": 62, "pattern": "^[a-z2-7]{56}\\.onion$" }, "descriptor_revision": { "type": "integer", "minimum": 1, "maximum": 9007199254740991 }, "previous_descriptor_hash": { "anyOf": [{ "type": "string", "minLength": 71, "maxLength": 71, "pattern": "^sha256:[0-9a-f]{64}$" }, { "type": "null" }] }, "supported_protocol_versions": { "type": "array", "items": { "type": "integer", "minimum": 1, "maximum": 9007199254740991 }, "minItems": 1, "maxItems": 16, "uniqueItems": true, "contains": { "const": 1 } }, "capabilities": { "type": "array", "items": { "type": "string", "minLength": 1, "maxLength": 64, "pattern": "^[a-z0-9][a-z0-9.-]*$" }, "maxItems": 32, "uniqueItems": true }, "software": { "type": "object", "properties": { "name": { "type": "string", "minLength": 1, "maxLength": 64, "pattern": "^[^\0-\x7F-\x9F]*$" }, "version": { "type": "string", "minLength": 1, "maxLength": 64, "pattern": "^[^\0-\x7F-\x9F]*$" } }, "required": ["name", "version"], "additionalProperties": false }, "signature": { "type": "string", "minLength": 97, "maxLength": 97, "pattern": "^ed25519sig:[A-Za-z0-9_-]{86}$" } }, "required": ["protocol", "protocol_version", "record_type", "node_identity_public_key", "node_onion", "descriptor_revision", "previous_descriptor_hash", "supported_protocol_versions", "capabilities", "software", "signature"], "additionalProperties": false, "$schema": "https://json-schema.org/draft/2020-12/schema", "$id": "https://github.com/ShawnJeromeMcCray/Onion-Service-Discovery/schemas/draft-v1/node_descriptor.json", "title": "Onion Service Discovery node_descriptor", "$comment": "Implementation draft; protocol freeze and human crypto approval are outstanding.", "allOf": [{ "if": { "properties": { "descriptor_revision": { "const": 1 } } }, "then": { "properties": { "previous_descriptor_hash": { "type": "null" } } }, "else": { "properties": { "previous_descriptor_hash": { "type": "string", "minLength": 71, "maxLength": 71, "pattern": "^sha256:[0-9a-f]{64}$" } } } }] };
var pattern14 = new RegExp("^[a-z0-9][a-z0-9.-]*$", "u");
var pattern15 = new RegExp("^[^\0-\x7F-\x9F]*$", "u");
function validate21(data, { instancePath = "", parentData, parentDataProperty, rootData = data, dynamicAnchors = {} } = {}) {
  ;
  let vErrors = null;
  let errors = 0;
  const evaluated0 = validate21.evaluated;
  if (evaluated0.dynamicProps) {
    evaluated0.props = void 0;
  }
  if (evaluated0.dynamicItems) {
    evaluated0.items = void 0;
  }
  const _errs3 = errors;
  let valid1 = true;
  const _errs4 = errors;
  if (data && typeof data == "object" && !Array.isArray(data)) {
    if (data.descriptor_revision !== void 0) {
      if (1 !== data.descriptor_revision) {
        const err0 = {};
        if (vErrors === null) {
          vErrors = [err0];
        } else {
          vErrors.push(err0);
        }
        errors++;
      }
    }
  }
  var _valid0 = _errs4 === errors;
  errors = _errs3;
  if (vErrors !== null) {
    if (_errs3) {
      vErrors.length = _errs3;
    } else {
      vErrors = null;
    }
  }
  let ifClause0;
  if (_valid0) {
    const _errs6 = errors;
    if (data && typeof data == "object" && !Array.isArray(data)) {
      if (data.previous_descriptor_hash !== void 0) {
        if (data.previous_descriptor_hash !== null) {
          validate21.errors = [{ instancePath: instancePath + "/previous_descriptor_hash", schemaPath: "#/allOf/0/then/properties/previous_descriptor_hash/type", keyword: "type", params: { type: "null" }, message: "must be null" }];
          return false;
        }
      }
    }
    var _valid0 = _errs6 === errors;
    valid1 = _valid0;
    if (valid1) {
      var props0 = {};
      props0.previous_descriptor_hash = true;
      props0.descriptor_revision = true;
    }
    ifClause0 = "then";
  } else {
    const _errs9 = errors;
    if (data && typeof data == "object" && !Array.isArray(data)) {
      if (data.previous_descriptor_hash !== void 0) {
        let data2 = data.previous_descriptor_hash;
        const _errs10 = errors;
        if (errors === _errs10) {
          if (typeof data2 === "string") {
            if (func2(data2) > 71) {
              validate21.errors = [{ instancePath: instancePath + "/previous_descriptor_hash", schemaPath: "#/allOf/0/else/properties/previous_descriptor_hash/maxLength", keyword: "maxLength", params: { limit: 71 }, message: "must NOT have more than 71 characters" }];
              return false;
            } else {
              if (func2(data2) < 71) {
                validate21.errors = [{ instancePath: instancePath + "/previous_descriptor_hash", schemaPath: "#/allOf/0/else/properties/previous_descriptor_hash/minLength", keyword: "minLength", params: { limit: 71 }, message: "must NOT have fewer than 71 characters" }];
                return false;
              } else {
                if (!pattern7.test(data2)) {
                  validate21.errors = [{ instancePath: instancePath + "/previous_descriptor_hash", schemaPath: "#/allOf/0/else/properties/previous_descriptor_hash/pattern", keyword: "pattern", params: { pattern: "^sha256:[0-9a-f]{64}$" }, message: 'must match pattern "^sha256:[0-9a-f]{64}$"' }];
                  return false;
                }
              }
            }
          } else {
            validate21.errors = [{ instancePath: instancePath + "/previous_descriptor_hash", schemaPath: "#/allOf/0/else/properties/previous_descriptor_hash/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
            return false;
          }
        }
      }
    }
    var _valid0 = _errs9 === errors;
    valid1 = _valid0;
    if (valid1) {
      if (props0 !== true) {
        props0 = props0 || {};
        props0.previous_descriptor_hash = true;
      }
    }
    ifClause0 = "else";
  }
  if (!valid1) {
    const err1 = { instancePath, schemaPath: "#/allOf/0/if", keyword: "if", params: { failingKeyword: ifClause0 }, message: 'must match "' + ifClause0 + '" schema' };
    if (vErrors === null) {
      vErrors = [err1];
    } else {
      vErrors.push(err1);
    }
    errors++;
    validate21.errors = vErrors;
    return false;
  }
  if (errors === 0) {
    if (data && typeof data == "object" && !Array.isArray(data)) {
      let missing0;
      if (data.protocol === void 0 && (missing0 = "protocol") || data.protocol_version === void 0 && (missing0 = "protocol_version") || data.record_type === void 0 && (missing0 = "record_type") || data.node_identity_public_key === void 0 && (missing0 = "node_identity_public_key") || data.node_onion === void 0 && (missing0 = "node_onion") || data.descriptor_revision === void 0 && (missing0 = "descriptor_revision") || data.previous_descriptor_hash === void 0 && (missing0 = "previous_descriptor_hash") || data.supported_protocol_versions === void 0 && (missing0 = "supported_protocol_versions") || data.capabilities === void 0 && (missing0 = "capabilities") || data.software === void 0 && (missing0 = "software") || data.signature === void 0 && (missing0 = "signature")) {
        validate21.errors = [{ instancePath, schemaPath: "#/required", keyword: "required", params: { missingProperty: missing0 }, message: "must have required property '" + missing0 + "'" }];
        return false;
      } else {
        const _errs12 = errors;
        for (const key0 in data) {
          if (!func1.call(schema32.properties, key0)) {
            validate21.errors = [{ instancePath, schemaPath: "#/additionalProperties", keyword: "additionalProperties", params: { additionalProperty: key0 }, message: "must NOT have additional properties" }];
            return false;
            break;
          }
        }
        if (_errs12 === errors) {
          if (data.protocol !== void 0) {
            const _errs13 = errors;
            if ("Onion Service Discovery" !== data.protocol) {
              validate21.errors = [{ instancePath: instancePath + "/protocol", schemaPath: "#/properties/protocol/const", keyword: "const", params: { allowedValue: "Onion Service Discovery" }, message: "must be equal to constant" }];
              return false;
            }
            var valid5 = _errs13 === errors;
          } else {
            var valid5 = true;
          }
          if (valid5) {
            if (data.protocol_version !== void 0) {
              const _errs14 = errors;
              if (1 !== data.protocol_version) {
                validate21.errors = [{ instancePath: instancePath + "/protocol_version", schemaPath: "#/properties/protocol_version/const", keyword: "const", params: { allowedValue: 1 }, message: "must be equal to constant" }];
                return false;
              }
              var valid5 = _errs14 === errors;
            } else {
              var valid5 = true;
            }
            if (valid5) {
              if (data.record_type !== void 0) {
                const _errs15 = errors;
                if ("node_descriptor" !== data.record_type) {
                  validate21.errors = [{ instancePath: instancePath + "/record_type", schemaPath: "#/properties/record_type/const", keyword: "const", params: { allowedValue: "node_descriptor" }, message: "must be equal to constant" }];
                  return false;
                }
                var valid5 = _errs15 === errors;
              } else {
                var valid5 = true;
              }
              if (valid5) {
                if (data.node_identity_public_key !== void 0) {
                  let data6 = data.node_identity_public_key;
                  const _errs16 = errors;
                  if (errors === _errs16) {
                    if (typeof data6 === "string") {
                      if (func2(data6) > 51) {
                        validate21.errors = [{ instancePath: instancePath + "/node_identity_public_key", schemaPath: "#/properties/node_identity_public_key/maxLength", keyword: "maxLength", params: { limit: 51 }, message: "must NOT have more than 51 characters" }];
                        return false;
                      } else {
                        if (func2(data6) < 51) {
                          validate21.errors = [{ instancePath: instancePath + "/node_identity_public_key", schemaPath: "#/properties/node_identity_public_key/minLength", keyword: "minLength", params: { limit: 51 }, message: "must NOT have fewer than 51 characters" }];
                          return false;
                        } else {
                          if (!pattern4.test(data6)) {
                            validate21.errors = [{ instancePath: instancePath + "/node_identity_public_key", schemaPath: "#/properties/node_identity_public_key/pattern", keyword: "pattern", params: { pattern: "^ed25519:[A-Za-z0-9_-]{43}$" }, message: 'must match pattern "^ed25519:[A-Za-z0-9_-]{43}$"' }];
                            return false;
                          }
                        }
                      }
                    } else {
                      validate21.errors = [{ instancePath: instancePath + "/node_identity_public_key", schemaPath: "#/properties/node_identity_public_key/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                      return false;
                    }
                  }
                  var valid5 = _errs16 === errors;
                } else {
                  var valid5 = true;
                }
                if (valid5) {
                  if (data.node_onion !== void 0) {
                    let data7 = data.node_onion;
                    const _errs18 = errors;
                    if (errors === _errs18) {
                      if (typeof data7 === "string") {
                        if (func2(data7) > 62) {
                          validate21.errors = [{ instancePath: instancePath + "/node_onion", schemaPath: "#/properties/node_onion/maxLength", keyword: "maxLength", params: { limit: 62 }, message: "must NOT have more than 62 characters" }];
                          return false;
                        } else {
                          if (func2(data7) < 62) {
                            validate21.errors = [{ instancePath: instancePath + "/node_onion", schemaPath: "#/properties/node_onion/minLength", keyword: "minLength", params: { limit: 62 }, message: "must NOT have fewer than 62 characters" }];
                            return false;
                          } else {
                            if (!pattern5.test(data7)) {
                              validate21.errors = [{ instancePath: instancePath + "/node_onion", schemaPath: "#/properties/node_onion/pattern", keyword: "pattern", params: { pattern: "^[a-z2-7]{56}\\.onion$" }, message: 'must match pattern "^[a-z2-7]{56}\\.onion$"' }];
                              return false;
                            }
                          }
                        }
                      } else {
                        validate21.errors = [{ instancePath: instancePath + "/node_onion", schemaPath: "#/properties/node_onion/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                        return false;
                      }
                    }
                    var valid5 = _errs18 === errors;
                  } else {
                    var valid5 = true;
                  }
                  if (valid5) {
                    if (data.descriptor_revision !== void 0) {
                      let data8 = data.descriptor_revision;
                      const _errs20 = errors;
                      if (!(typeof data8 == "number" && (!(data8 % 1) && !isNaN(data8)) && isFinite(data8))) {
                        validate21.errors = [{ instancePath: instancePath + "/descriptor_revision", schemaPath: "#/properties/descriptor_revision/type", keyword: "type", params: { type: "integer" }, message: "must be integer" }];
                        return false;
                      }
                      if (errors === _errs20) {
                        if (typeof data8 == "number" && isFinite(data8)) {
                          if (data8 > 9007199254740991 || isNaN(data8)) {
                            validate21.errors = [{ instancePath: instancePath + "/descriptor_revision", schemaPath: "#/properties/descriptor_revision/maximum", keyword: "maximum", params: { comparison: "<=", limit: 9007199254740991 }, message: "must be <= 9007199254740991" }];
                            return false;
                          } else {
                            if (data8 < 1 || isNaN(data8)) {
                              validate21.errors = [{ instancePath: instancePath + "/descriptor_revision", schemaPath: "#/properties/descriptor_revision/minimum", keyword: "minimum", params: { comparison: ">=", limit: 1 }, message: "must be >= 1" }];
                              return false;
                            }
                          }
                        }
                      }
                      var valid5 = _errs20 === errors;
                    } else {
                      var valid5 = true;
                    }
                    if (valid5) {
                      if (data.previous_descriptor_hash !== void 0) {
                        let data9 = data.previous_descriptor_hash;
                        const _errs22 = errors;
                        const _errs23 = errors;
                        let valid6 = false;
                        const _errs24 = errors;
                        if (errors === _errs24) {
                          if (typeof data9 === "string") {
                            if (func2(data9) > 71) {
                              const err2 = { instancePath: instancePath + "/previous_descriptor_hash", schemaPath: "#/properties/previous_descriptor_hash/anyOf/0/maxLength", keyword: "maxLength", params: { limit: 71 }, message: "must NOT have more than 71 characters" };
                              if (vErrors === null) {
                                vErrors = [err2];
                              } else {
                                vErrors.push(err2);
                              }
                              errors++;
                            } else {
                              if (func2(data9) < 71) {
                                const err3 = { instancePath: instancePath + "/previous_descriptor_hash", schemaPath: "#/properties/previous_descriptor_hash/anyOf/0/minLength", keyword: "minLength", params: { limit: 71 }, message: "must NOT have fewer than 71 characters" };
                                if (vErrors === null) {
                                  vErrors = [err3];
                                } else {
                                  vErrors.push(err3);
                                }
                                errors++;
                              } else {
                                if (!pattern7.test(data9)) {
                                  const err4 = { instancePath: instancePath + "/previous_descriptor_hash", schemaPath: "#/properties/previous_descriptor_hash/anyOf/0/pattern", keyword: "pattern", params: { pattern: "^sha256:[0-9a-f]{64}$" }, message: 'must match pattern "^sha256:[0-9a-f]{64}$"' };
                                  if (vErrors === null) {
                                    vErrors = [err4];
                                  } else {
                                    vErrors.push(err4);
                                  }
                                  errors++;
                                }
                              }
                            }
                          } else {
                            const err5 = { instancePath: instancePath + "/previous_descriptor_hash", schemaPath: "#/properties/previous_descriptor_hash/anyOf/0/type", keyword: "type", params: { type: "string" }, message: "must be string" };
                            if (vErrors === null) {
                              vErrors = [err5];
                            } else {
                              vErrors.push(err5);
                            }
                            errors++;
                          }
                        }
                        var _valid1 = _errs24 === errors;
                        valid6 = valid6 || _valid1;
                        const _errs26 = errors;
                        if (data9 !== null) {
                          const err6 = { instancePath: instancePath + "/previous_descriptor_hash", schemaPath: "#/properties/previous_descriptor_hash/anyOf/1/type", keyword: "type", params: { type: "null" }, message: "must be null" };
                          if (vErrors === null) {
                            vErrors = [err6];
                          } else {
                            vErrors.push(err6);
                          }
                          errors++;
                        }
                        var _valid1 = _errs26 === errors;
                        valid6 = valid6 || _valid1;
                        if (!valid6) {
                          const err7 = { instancePath: instancePath + "/previous_descriptor_hash", schemaPath: "#/properties/previous_descriptor_hash/anyOf", keyword: "anyOf", params: {}, message: "must match a schema in anyOf" };
                          if (vErrors === null) {
                            vErrors = [err7];
                          } else {
                            vErrors.push(err7);
                          }
                          errors++;
                          validate21.errors = vErrors;
                          return false;
                        } else {
                          errors = _errs23;
                          if (vErrors !== null) {
                            if (_errs23) {
                              vErrors.length = _errs23;
                            } else {
                              vErrors = null;
                            }
                          }
                        }
                        var valid5 = _errs22 === errors;
                      } else {
                        var valid5 = true;
                      }
                      if (valid5) {
                        if (data.supported_protocol_versions !== void 0) {
                          let data10 = data.supported_protocol_versions;
                          const _errs28 = errors;
                          if (errors === _errs28) {
                            if (Array.isArray(data10)) {
                              if (data10.length > 16) {
                                validate21.errors = [{ instancePath: instancePath + "/supported_protocol_versions", schemaPath: "#/properties/supported_protocol_versions/maxItems", keyword: "maxItems", params: { limit: 16 }, message: "must NOT have more than 16 items" }];
                                return false;
                              } else {
                                if (data10.length < 1) {
                                  validate21.errors = [{ instancePath: instancePath + "/supported_protocol_versions", schemaPath: "#/properties/supported_protocol_versions/minItems", keyword: "minItems", params: { limit: 1 }, message: "must NOT have fewer than 1 items" }];
                                  return false;
                                } else {
                                  var valid7 = true;
                                  const len0 = data10.length;
                                  for (let i0 = 0; i0 < len0; i0++) {
                                    let data11 = data10[i0];
                                    const _errs30 = errors;
                                    if (!(typeof data11 == "number" && (!(data11 % 1) && !isNaN(data11)) && isFinite(data11))) {
                                      validate21.errors = [{ instancePath: instancePath + "/supported_protocol_versions/" + i0, schemaPath: "#/properties/supported_protocol_versions/items/type", keyword: "type", params: { type: "integer" }, message: "must be integer" }];
                                      return false;
                                    }
                                    if (errors === _errs30) {
                                      if (typeof data11 == "number" && isFinite(data11)) {
                                        if (data11 > 9007199254740991 || isNaN(data11)) {
                                          validate21.errors = [{ instancePath: instancePath + "/supported_protocol_versions/" + i0, schemaPath: "#/properties/supported_protocol_versions/items/maximum", keyword: "maximum", params: { comparison: "<=", limit: 9007199254740991 }, message: "must be <= 9007199254740991" }];
                                          return false;
                                        } else {
                                          if (data11 < 1 || isNaN(data11)) {
                                            validate21.errors = [{ instancePath: instancePath + "/supported_protocol_versions/" + i0, schemaPath: "#/properties/supported_protocol_versions/items/minimum", keyword: "minimum", params: { comparison: ">=", limit: 1 }, message: "must be >= 1" }];
                                            return false;
                                          }
                                        }
                                      }
                                    }
                                    var valid7 = _errs30 === errors;
                                    if (!valid7) {
                                      break;
                                    }
                                  }
                                  if (valid7) {
                                    const _errs32 = errors;
                                    const len1 = data10.length;
                                    for (let i1 = 0; i1 < len1; i1++) {
                                      const _errs33 = errors;
                                      if (1 !== data10[i1]) {
                                        const err8 = { instancePath: instancePath + "/supported_protocol_versions/" + i1, schemaPath: "#/properties/supported_protocol_versions/contains/const", keyword: "const", params: { allowedValue: 1 }, message: "must be equal to constant" };
                                        if (vErrors === null) {
                                          vErrors = [err8];
                                        } else {
                                          vErrors.push(err8);
                                        }
                                        errors++;
                                      }
                                      var valid8 = _errs33 === errors;
                                      if (valid8) {
                                        break;
                                      }
                                    }
                                    if (!valid8) {
                                      validate21.errors = [{ instancePath: instancePath + "/supported_protocol_versions", schemaPath: "#/properties/supported_protocol_versions/contains", keyword: "contains", params: { minContains: 1 }, message: "must contain at least 1 valid item(s)" }];
                                      return false;
                                    } else {
                                      errors = _errs32;
                                      if (vErrors !== null) {
                                        if (_errs32) {
                                          vErrors.length = _errs32;
                                        } else {
                                          vErrors = null;
                                        }
                                      }
                                      let i2 = data10.length;
                                      let j0;
                                      if (i2 > 1) {
                                        const indices0 = {};
                                        for (; i2--; ) {
                                          let item0 = data10[i2];
                                          if (!(typeof item0 == "number" && (!(item0 % 1) && !isNaN(item0)) && isFinite(item0))) {
                                            continue;
                                          }
                                          if (typeof indices0[item0] == "number") {
                                            j0 = indices0[item0];
                                            validate21.errors = [{ instancePath: instancePath + "/supported_protocol_versions", schemaPath: "#/properties/supported_protocol_versions/uniqueItems", keyword: "uniqueItems", params: { i: i2, j: j0 }, message: "must NOT have duplicate items (items ## " + j0 + " and " + i2 + " are identical)" }];
                                            return false;
                                            break;
                                          }
                                          indices0[item0] = i2;
                                        }
                                      }
                                    }
                                  }
                                }
                              }
                            } else {
                              validate21.errors = [{ instancePath: instancePath + "/supported_protocol_versions", schemaPath: "#/properties/supported_protocol_versions/type", keyword: "type", params: { type: "array" }, message: "must be array" }];
                              return false;
                            }
                          }
                          var valid5 = _errs28 === errors;
                        } else {
                          var valid5 = true;
                        }
                        if (valid5) {
                          if (data.capabilities !== void 0) {
                            let data13 = data.capabilities;
                            const _errs34 = errors;
                            if (errors === _errs34) {
                              if (Array.isArray(data13)) {
                                if (data13.length > 32) {
                                  validate21.errors = [{ instancePath: instancePath + "/capabilities", schemaPath: "#/properties/capabilities/maxItems", keyword: "maxItems", params: { limit: 32 }, message: "must NOT have more than 32 items" }];
                                  return false;
                                } else {
                                  var valid10 = true;
                                  const len2 = data13.length;
                                  for (let i3 = 0; i3 < len2; i3++) {
                                    let data14 = data13[i3];
                                    const _errs36 = errors;
                                    if (errors === _errs36) {
                                      if (typeof data14 === "string") {
                                        if (func2(data14) > 64) {
                                          validate21.errors = [{ instancePath: instancePath + "/capabilities/" + i3, schemaPath: "#/properties/capabilities/items/maxLength", keyword: "maxLength", params: { limit: 64 }, message: "must NOT have more than 64 characters" }];
                                          return false;
                                        } else {
                                          if (func2(data14) < 1) {
                                            validate21.errors = [{ instancePath: instancePath + "/capabilities/" + i3, schemaPath: "#/properties/capabilities/items/minLength", keyword: "minLength", params: { limit: 1 }, message: "must NOT have fewer than 1 characters" }];
                                            return false;
                                          } else {
                                            if (!pattern14.test(data14)) {
                                              validate21.errors = [{ instancePath: instancePath + "/capabilities/" + i3, schemaPath: "#/properties/capabilities/items/pattern", keyword: "pattern", params: { pattern: "^[a-z0-9][a-z0-9.-]*$" }, message: 'must match pattern "^[a-z0-9][a-z0-9.-]*$"' }];
                                              return false;
                                            }
                                          }
                                        }
                                      } else {
                                        validate21.errors = [{ instancePath: instancePath + "/capabilities/" + i3, schemaPath: "#/properties/capabilities/items/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                        return false;
                                      }
                                    }
                                    var valid10 = _errs36 === errors;
                                    if (!valid10) {
                                      break;
                                    }
                                  }
                                  if (valid10) {
                                    let i4 = data13.length;
                                    let j1;
                                    if (i4 > 1) {
                                      const indices1 = {};
                                      for (; i4--; ) {
                                        let item1 = data13[i4];
                                        if (typeof item1 !== "string") {
                                          continue;
                                        }
                                        if (typeof indices1[item1] == "number") {
                                          j1 = indices1[item1];
                                          validate21.errors = [{ instancePath: instancePath + "/capabilities", schemaPath: "#/properties/capabilities/uniqueItems", keyword: "uniqueItems", params: { i: i4, j: j1 }, message: "must NOT have duplicate items (items ## " + j1 + " and " + i4 + " are identical)" }];
                                          return false;
                                          break;
                                        }
                                        indices1[item1] = i4;
                                      }
                                    }
                                  }
                                }
                              } else {
                                validate21.errors = [{ instancePath: instancePath + "/capabilities", schemaPath: "#/properties/capabilities/type", keyword: "type", params: { type: "array" }, message: "must be array" }];
                                return false;
                              }
                            }
                            var valid5 = _errs34 === errors;
                          } else {
                            var valid5 = true;
                          }
                          if (valid5) {
                            if (data.software !== void 0) {
                              let data15 = data.software;
                              const _errs38 = errors;
                              if (errors === _errs38) {
                                if (data15 && typeof data15 == "object" && !Array.isArray(data15)) {
                                  let missing1;
                                  if (data15.name === void 0 && (missing1 = "name") || data15.version === void 0 && (missing1 = "version")) {
                                    validate21.errors = [{ instancePath: instancePath + "/software", schemaPath: "#/properties/software/required", keyword: "required", params: { missingProperty: missing1 }, message: "must have required property '" + missing1 + "'" }];
                                    return false;
                                  } else {
                                    const _errs40 = errors;
                                    for (const key1 in data15) {
                                      if (!(key1 === "name" || key1 === "version")) {
                                        validate21.errors = [{ instancePath: instancePath + "/software", schemaPath: "#/properties/software/additionalProperties", keyword: "additionalProperties", params: { additionalProperty: key1 }, message: "must NOT have additional properties" }];
                                        return false;
                                        break;
                                      }
                                    }
                                    if (_errs40 === errors) {
                                      if (data15.name !== void 0) {
                                        let data16 = data15.name;
                                        const _errs41 = errors;
                                        if (errors === _errs41) {
                                          if (typeof data16 === "string") {
                                            if (func2(data16) > 64) {
                                              validate21.errors = [{ instancePath: instancePath + "/software/name", schemaPath: "#/properties/software/properties/name/maxLength", keyword: "maxLength", params: { limit: 64 }, message: "must NOT have more than 64 characters" }];
                                              return false;
                                            } else {
                                              if (func2(data16) < 1) {
                                                validate21.errors = [{ instancePath: instancePath + "/software/name", schemaPath: "#/properties/software/properties/name/minLength", keyword: "minLength", params: { limit: 1 }, message: "must NOT have fewer than 1 characters" }];
                                                return false;
                                              } else {
                                                if (!pattern15.test(data16)) {
                                                  validate21.errors = [{ instancePath: instancePath + "/software/name", schemaPath: "#/properties/software/properties/name/pattern", keyword: "pattern", params: { pattern: "^[^\0-\x7F-\x9F]*$" }, message: 'must match pattern "^[^\0-\x7F-\x9F]*$"' }];
                                                  return false;
                                                }
                                              }
                                            }
                                          } else {
                                            validate21.errors = [{ instancePath: instancePath + "/software/name", schemaPath: "#/properties/software/properties/name/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                            return false;
                                          }
                                        }
                                        var valid12 = _errs41 === errors;
                                      } else {
                                        var valid12 = true;
                                      }
                                      if (valid12) {
                                        if (data15.version !== void 0) {
                                          let data17 = data15.version;
                                          const _errs43 = errors;
                                          if (errors === _errs43) {
                                            if (typeof data17 === "string") {
                                              if (func2(data17) > 64) {
                                                validate21.errors = [{ instancePath: instancePath + "/software/version", schemaPath: "#/properties/software/properties/version/maxLength", keyword: "maxLength", params: { limit: 64 }, message: "must NOT have more than 64 characters" }];
                                                return false;
                                              } else {
                                                if (func2(data17) < 1) {
                                                  validate21.errors = [{ instancePath: instancePath + "/software/version", schemaPath: "#/properties/software/properties/version/minLength", keyword: "minLength", params: { limit: 1 }, message: "must NOT have fewer than 1 characters" }];
                                                  return false;
                                                } else {
                                                  if (!pattern15.test(data17)) {
                                                    validate21.errors = [{ instancePath: instancePath + "/software/version", schemaPath: "#/properties/software/properties/version/pattern", keyword: "pattern", params: { pattern: "^[^\0-\x7F-\x9F]*$" }, message: 'must match pattern "^[^\0-\x7F-\x9F]*$"' }];
                                                    return false;
                                                  }
                                                }
                                              }
                                            } else {
                                              validate21.errors = [{ instancePath: instancePath + "/software/version", schemaPath: "#/properties/software/properties/version/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                              return false;
                                            }
                                          }
                                          var valid12 = _errs43 === errors;
                                        } else {
                                          var valid12 = true;
                                        }
                                      }
                                    }
                                  }
                                } else {
                                  validate21.errors = [{ instancePath: instancePath + "/software", schemaPath: "#/properties/software/type", keyword: "type", params: { type: "object" }, message: "must be object" }];
                                  return false;
                                }
                              }
                              var valid5 = _errs38 === errors;
                            } else {
                              var valid5 = true;
                            }
                            if (valid5) {
                              if (data.signature !== void 0) {
                                let data18 = data.signature;
                                const _errs45 = errors;
                                if (errors === _errs45) {
                                  if (typeof data18 === "string") {
                                    if (func2(data18) > 97) {
                                      validate21.errors = [{ instancePath: instancePath + "/signature", schemaPath: "#/properties/signature/maxLength", keyword: "maxLength", params: { limit: 97 }, message: "must NOT have more than 97 characters" }];
                                      return false;
                                    } else {
                                      if (func2(data18) < 97) {
                                        validate21.errors = [{ instancePath: instancePath + "/signature", schemaPath: "#/properties/signature/minLength", keyword: "minLength", params: { limit: 97 }, message: "must NOT have fewer than 97 characters" }];
                                        return false;
                                      } else {
                                        if (!pattern9.test(data18)) {
                                          validate21.errors = [{ instancePath: instancePath + "/signature", schemaPath: "#/properties/signature/pattern", keyword: "pattern", params: { pattern: "^ed25519sig:[A-Za-z0-9_-]{86}$" }, message: 'must match pattern "^ed25519sig:[A-Za-z0-9_-]{86}$"' }];
                                          return false;
                                        }
                                      }
                                    }
                                  } else {
                                    validate21.errors = [{ instancePath: instancePath + "/signature", schemaPath: "#/properties/signature/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                    return false;
                                  }
                                }
                                var valid5 = _errs45 === errors;
                              } else {
                                var valid5 = true;
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    } else {
      validate21.errors = [{ instancePath, schemaPath: "#/type", keyword: "type", params: { type: "object" }, message: "must be object" }];
      return false;
    }
  }
  validate21.errors = vErrors;
  return errors === 0;
}
validate21.evaluated = { "props": true, "dynamicProps": false, "dynamicItems": false };
var onion_service_migration = validate22;
var schema33 = { "type": "object", "properties": { "protocol": { "const": "Onion Service Discovery" }, "protocol_version": { "const": 1 }, "record_type": { "const": "onion_service_migration" }, "service_identity_public_key": { "type": "string", "minLength": 51, "maxLength": 51, "pattern": "^ed25519:[A-Za-z0-9_-]{43}$" }, "from_onion": { "type": "string", "minLength": 62, "maxLength": 62, "pattern": "^[a-z2-7]{56}\\.onion$" }, "to_onion": { "type": "string", "minLength": 62, "maxLength": 62, "pattern": "^[a-z2-7]{56}\\.onion$" }, "effective_revision": { "type": "integer", "minimum": 1, "maximum": 9007199254740991 }, "previous_state_hash": { "type": "string", "minLength": 71, "maxLength": 71, "pattern": "^sha256:[0-9a-f]{64}$" }, "service_identity_signature": { "type": "string", "minLength": 97, "maxLength": 97, "pattern": "^ed25519sig:[A-Za-z0-9_-]{86}$" } }, "required": ["protocol", "protocol_version", "record_type", "service_identity_public_key", "from_onion", "to_onion", "effective_revision", "previous_state_hash", "service_identity_signature"], "additionalProperties": false, "$schema": "https://json-schema.org/draft/2020-12/schema", "$id": "https://github.com/ShawnJeromeMcCray/Onion-Service-Discovery/schemas/draft-v1/onion_service_migration.json", "title": "Onion Service Discovery onion_service_migration", "$comment": "Implementation draft; protocol freeze and human crypto approval are outstanding." };
function validate22(data, { instancePath = "", parentData, parentDataProperty, rootData = data, dynamicAnchors = {} } = {}) {
  ;
  let vErrors = null;
  let errors = 0;
  const evaluated0 = validate22.evaluated;
  if (evaluated0.dynamicProps) {
    evaluated0.props = void 0;
  }
  if (evaluated0.dynamicItems) {
    evaluated0.items = void 0;
  }
  if (errors === 0) {
    if (data && typeof data == "object" && !Array.isArray(data)) {
      let missing0;
      if (data.protocol === void 0 && (missing0 = "protocol") || data.protocol_version === void 0 && (missing0 = "protocol_version") || data.record_type === void 0 && (missing0 = "record_type") || data.service_identity_public_key === void 0 && (missing0 = "service_identity_public_key") || data.from_onion === void 0 && (missing0 = "from_onion") || data.to_onion === void 0 && (missing0 = "to_onion") || data.effective_revision === void 0 && (missing0 = "effective_revision") || data.previous_state_hash === void 0 && (missing0 = "previous_state_hash") || data.service_identity_signature === void 0 && (missing0 = "service_identity_signature")) {
        validate22.errors = [{ instancePath, schemaPath: "#/required", keyword: "required", params: { missingProperty: missing0 }, message: "must have required property '" + missing0 + "'" }];
        return false;
      } else {
        const _errs2 = errors;
        for (const key0 in data) {
          if (!func1.call(schema33.properties, key0)) {
            validate22.errors = [{ instancePath, schemaPath: "#/additionalProperties", keyword: "additionalProperties", params: { additionalProperty: key0 }, message: "must NOT have additional properties" }];
            return false;
            break;
          }
        }
        if (_errs2 === errors) {
          if (data.protocol !== void 0) {
            const _errs3 = errors;
            if ("Onion Service Discovery" !== data.protocol) {
              validate22.errors = [{ instancePath: instancePath + "/protocol", schemaPath: "#/properties/protocol/const", keyword: "const", params: { allowedValue: "Onion Service Discovery" }, message: "must be equal to constant" }];
              return false;
            }
            var valid0 = _errs3 === errors;
          } else {
            var valid0 = true;
          }
          if (valid0) {
            if (data.protocol_version !== void 0) {
              const _errs4 = errors;
              if (1 !== data.protocol_version) {
                validate22.errors = [{ instancePath: instancePath + "/protocol_version", schemaPath: "#/properties/protocol_version/const", keyword: "const", params: { allowedValue: 1 }, message: "must be equal to constant" }];
                return false;
              }
              var valid0 = _errs4 === errors;
            } else {
              var valid0 = true;
            }
            if (valid0) {
              if (data.record_type !== void 0) {
                const _errs5 = errors;
                if ("onion_service_migration" !== data.record_type) {
                  validate22.errors = [{ instancePath: instancePath + "/record_type", schemaPath: "#/properties/record_type/const", keyword: "const", params: { allowedValue: "onion_service_migration" }, message: "must be equal to constant" }];
                  return false;
                }
                var valid0 = _errs5 === errors;
              } else {
                var valid0 = true;
              }
              if (valid0) {
                if (data.service_identity_public_key !== void 0) {
                  let data3 = data.service_identity_public_key;
                  const _errs6 = errors;
                  if (errors === _errs6) {
                    if (typeof data3 === "string") {
                      if (func2(data3) > 51) {
                        validate22.errors = [{ instancePath: instancePath + "/service_identity_public_key", schemaPath: "#/properties/service_identity_public_key/maxLength", keyword: "maxLength", params: { limit: 51 }, message: "must NOT have more than 51 characters" }];
                        return false;
                      } else {
                        if (func2(data3) < 51) {
                          validate22.errors = [{ instancePath: instancePath + "/service_identity_public_key", schemaPath: "#/properties/service_identity_public_key/minLength", keyword: "minLength", params: { limit: 51 }, message: "must NOT have fewer than 51 characters" }];
                          return false;
                        } else {
                          if (!pattern4.test(data3)) {
                            validate22.errors = [{ instancePath: instancePath + "/service_identity_public_key", schemaPath: "#/properties/service_identity_public_key/pattern", keyword: "pattern", params: { pattern: "^ed25519:[A-Za-z0-9_-]{43}$" }, message: 'must match pattern "^ed25519:[A-Za-z0-9_-]{43}$"' }];
                            return false;
                          }
                        }
                      }
                    } else {
                      validate22.errors = [{ instancePath: instancePath + "/service_identity_public_key", schemaPath: "#/properties/service_identity_public_key/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                      return false;
                    }
                  }
                  var valid0 = _errs6 === errors;
                } else {
                  var valid0 = true;
                }
                if (valid0) {
                  if (data.from_onion !== void 0) {
                    let data4 = data.from_onion;
                    const _errs8 = errors;
                    if (errors === _errs8) {
                      if (typeof data4 === "string") {
                        if (func2(data4) > 62) {
                          validate22.errors = [{ instancePath: instancePath + "/from_onion", schemaPath: "#/properties/from_onion/maxLength", keyword: "maxLength", params: { limit: 62 }, message: "must NOT have more than 62 characters" }];
                          return false;
                        } else {
                          if (func2(data4) < 62) {
                            validate22.errors = [{ instancePath: instancePath + "/from_onion", schemaPath: "#/properties/from_onion/minLength", keyword: "minLength", params: { limit: 62 }, message: "must NOT have fewer than 62 characters" }];
                            return false;
                          } else {
                            if (!pattern5.test(data4)) {
                              validate22.errors = [{ instancePath: instancePath + "/from_onion", schemaPath: "#/properties/from_onion/pattern", keyword: "pattern", params: { pattern: "^[a-z2-7]{56}\\.onion$" }, message: 'must match pattern "^[a-z2-7]{56}\\.onion$"' }];
                              return false;
                            }
                          }
                        }
                      } else {
                        validate22.errors = [{ instancePath: instancePath + "/from_onion", schemaPath: "#/properties/from_onion/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                        return false;
                      }
                    }
                    var valid0 = _errs8 === errors;
                  } else {
                    var valid0 = true;
                  }
                  if (valid0) {
                    if (data.to_onion !== void 0) {
                      let data5 = data.to_onion;
                      const _errs10 = errors;
                      if (errors === _errs10) {
                        if (typeof data5 === "string") {
                          if (func2(data5) > 62) {
                            validate22.errors = [{ instancePath: instancePath + "/to_onion", schemaPath: "#/properties/to_onion/maxLength", keyword: "maxLength", params: { limit: 62 }, message: "must NOT have more than 62 characters" }];
                            return false;
                          } else {
                            if (func2(data5) < 62) {
                              validate22.errors = [{ instancePath: instancePath + "/to_onion", schemaPath: "#/properties/to_onion/minLength", keyword: "minLength", params: { limit: 62 }, message: "must NOT have fewer than 62 characters" }];
                              return false;
                            } else {
                              if (!pattern5.test(data5)) {
                                validate22.errors = [{ instancePath: instancePath + "/to_onion", schemaPath: "#/properties/to_onion/pattern", keyword: "pattern", params: { pattern: "^[a-z2-7]{56}\\.onion$" }, message: 'must match pattern "^[a-z2-7]{56}\\.onion$"' }];
                                return false;
                              }
                            }
                          }
                        } else {
                          validate22.errors = [{ instancePath: instancePath + "/to_onion", schemaPath: "#/properties/to_onion/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                          return false;
                        }
                      }
                      var valid0 = _errs10 === errors;
                    } else {
                      var valid0 = true;
                    }
                    if (valid0) {
                      if (data.effective_revision !== void 0) {
                        let data6 = data.effective_revision;
                        const _errs12 = errors;
                        if (!(typeof data6 == "number" && (!(data6 % 1) && !isNaN(data6)) && isFinite(data6))) {
                          validate22.errors = [{ instancePath: instancePath + "/effective_revision", schemaPath: "#/properties/effective_revision/type", keyword: "type", params: { type: "integer" }, message: "must be integer" }];
                          return false;
                        }
                        if (errors === _errs12) {
                          if (typeof data6 == "number" && isFinite(data6)) {
                            if (data6 > 9007199254740991 || isNaN(data6)) {
                              validate22.errors = [{ instancePath: instancePath + "/effective_revision", schemaPath: "#/properties/effective_revision/maximum", keyword: "maximum", params: { comparison: "<=", limit: 9007199254740991 }, message: "must be <= 9007199254740991" }];
                              return false;
                            } else {
                              if (data6 < 1 || isNaN(data6)) {
                                validate22.errors = [{ instancePath: instancePath + "/effective_revision", schemaPath: "#/properties/effective_revision/minimum", keyword: "minimum", params: { comparison: ">=", limit: 1 }, message: "must be >= 1" }];
                                return false;
                              }
                            }
                          }
                        }
                        var valid0 = _errs12 === errors;
                      } else {
                        var valid0 = true;
                      }
                      if (valid0) {
                        if (data.previous_state_hash !== void 0) {
                          let data7 = data.previous_state_hash;
                          const _errs14 = errors;
                          if (errors === _errs14) {
                            if (typeof data7 === "string") {
                              if (func2(data7) > 71) {
                                validate22.errors = [{ instancePath: instancePath + "/previous_state_hash", schemaPath: "#/properties/previous_state_hash/maxLength", keyword: "maxLength", params: { limit: 71 }, message: "must NOT have more than 71 characters" }];
                                return false;
                              } else {
                                if (func2(data7) < 71) {
                                  validate22.errors = [{ instancePath: instancePath + "/previous_state_hash", schemaPath: "#/properties/previous_state_hash/minLength", keyword: "minLength", params: { limit: 71 }, message: "must NOT have fewer than 71 characters" }];
                                  return false;
                                } else {
                                  if (!pattern7.test(data7)) {
                                    validate22.errors = [{ instancePath: instancePath + "/previous_state_hash", schemaPath: "#/properties/previous_state_hash/pattern", keyword: "pattern", params: { pattern: "^sha256:[0-9a-f]{64}$" }, message: 'must match pattern "^sha256:[0-9a-f]{64}$"' }];
                                    return false;
                                  }
                                }
                              }
                            } else {
                              validate22.errors = [{ instancePath: instancePath + "/previous_state_hash", schemaPath: "#/properties/previous_state_hash/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                              return false;
                            }
                          }
                          var valid0 = _errs14 === errors;
                        } else {
                          var valid0 = true;
                        }
                        if (valid0) {
                          if (data.service_identity_signature !== void 0) {
                            let data8 = data.service_identity_signature;
                            const _errs16 = errors;
                            if (errors === _errs16) {
                              if (typeof data8 === "string") {
                                if (func2(data8) > 97) {
                                  validate22.errors = [{ instancePath: instancePath + "/service_identity_signature", schemaPath: "#/properties/service_identity_signature/maxLength", keyword: "maxLength", params: { limit: 97 }, message: "must NOT have more than 97 characters" }];
                                  return false;
                                } else {
                                  if (func2(data8) < 97) {
                                    validate22.errors = [{ instancePath: instancePath + "/service_identity_signature", schemaPath: "#/properties/service_identity_signature/minLength", keyword: "minLength", params: { limit: 97 }, message: "must NOT have fewer than 97 characters" }];
                                    return false;
                                  } else {
                                    if (!pattern9.test(data8)) {
                                      validate22.errors = [{ instancePath: instancePath + "/service_identity_signature", schemaPath: "#/properties/service_identity_signature/pattern", keyword: "pattern", params: { pattern: "^ed25519sig:[A-Za-z0-9_-]{86}$" }, message: 'must match pattern "^ed25519sig:[A-Za-z0-9_-]{86}$"' }];
                                      return false;
                                    }
                                  }
                                }
                              } else {
                                validate22.errors = [{ instancePath: instancePath + "/service_identity_signature", schemaPath: "#/properties/service_identity_signature/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                return false;
                              }
                            }
                            var valid0 = _errs16 === errors;
                          } else {
                            var valid0 = true;
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    } else {
      validate22.errors = [{ instancePath, schemaPath: "#/type", keyword: "type", params: { type: "object" }, message: "must be object" }];
      return false;
    }
  }
  validate22.errors = vErrors;
  return errors === 0;
}
validate22.evaluated = { "props": true, "dynamicProps": false, "dynamicItems": false };
var service_identity_key_rotation = validate23;
var schema34 = { "type": "object", "properties": { "protocol": { "const": "Onion Service Discovery" }, "protocol_version": { "const": 1 }, "record_type": { "const": "service_identity_key_rotation" }, "onion": { "type": "string", "minLength": 62, "maxLength": 62, "pattern": "^[a-z2-7]{56}\\.onion$" }, "old_public_key": { "type": "string", "minLength": 51, "maxLength": 51, "pattern": "^ed25519:[A-Za-z0-9_-]{43}$" }, "new_public_key": { "type": "string", "minLength": 51, "maxLength": 51, "pattern": "^ed25519:[A-Za-z0-9_-]{43}$" }, "effective_revision": { "type": "integer", "minimum": 1, "maximum": 9007199254740991 }, "previous_state_hash": { "type": "string", "minLength": 71, "maxLength": 71, "pattern": "^sha256:[0-9a-f]{64}$" }, "new_key_confirmation": { "type": "string", "minLength": 97, "maxLength": 97, "pattern": "^ed25519sig:[A-Za-z0-9_-]{86}$" }, "old_key_signature": { "type": "string", "minLength": 97, "maxLength": 97, "pattern": "^ed25519sig:[A-Za-z0-9_-]{86}$" } }, "required": ["protocol", "protocol_version", "record_type", "onion", "old_public_key", "new_public_key", "effective_revision", "previous_state_hash", "new_key_confirmation", "old_key_signature"], "additionalProperties": false, "$schema": "https://json-schema.org/draft/2020-12/schema", "$id": "https://github.com/ShawnJeromeMcCray/Onion-Service-Discovery/schemas/draft-v1/service_identity_key_rotation.json", "title": "Onion Service Discovery service_identity_key_rotation", "$comment": "Implementation draft; protocol freeze and human crypto approval are outstanding." };
function validate23(data, { instancePath = "", parentData, parentDataProperty, rootData = data, dynamicAnchors = {} } = {}) {
  ;
  let vErrors = null;
  let errors = 0;
  const evaluated0 = validate23.evaluated;
  if (evaluated0.dynamicProps) {
    evaluated0.props = void 0;
  }
  if (evaluated0.dynamicItems) {
    evaluated0.items = void 0;
  }
  if (errors === 0) {
    if (data && typeof data == "object" && !Array.isArray(data)) {
      let missing0;
      if (data.protocol === void 0 && (missing0 = "protocol") || data.protocol_version === void 0 && (missing0 = "protocol_version") || data.record_type === void 0 && (missing0 = "record_type") || data.onion === void 0 && (missing0 = "onion") || data.old_public_key === void 0 && (missing0 = "old_public_key") || data.new_public_key === void 0 && (missing0 = "new_public_key") || data.effective_revision === void 0 && (missing0 = "effective_revision") || data.previous_state_hash === void 0 && (missing0 = "previous_state_hash") || data.new_key_confirmation === void 0 && (missing0 = "new_key_confirmation") || data.old_key_signature === void 0 && (missing0 = "old_key_signature")) {
        validate23.errors = [{ instancePath, schemaPath: "#/required", keyword: "required", params: { missingProperty: missing0 }, message: "must have required property '" + missing0 + "'" }];
        return false;
      } else {
        const _errs2 = errors;
        for (const key0 in data) {
          if (!func1.call(schema34.properties, key0)) {
            validate23.errors = [{ instancePath, schemaPath: "#/additionalProperties", keyword: "additionalProperties", params: { additionalProperty: key0 }, message: "must NOT have additional properties" }];
            return false;
            break;
          }
        }
        if (_errs2 === errors) {
          if (data.protocol !== void 0) {
            const _errs3 = errors;
            if ("Onion Service Discovery" !== data.protocol) {
              validate23.errors = [{ instancePath: instancePath + "/protocol", schemaPath: "#/properties/protocol/const", keyword: "const", params: { allowedValue: "Onion Service Discovery" }, message: "must be equal to constant" }];
              return false;
            }
            var valid0 = _errs3 === errors;
          } else {
            var valid0 = true;
          }
          if (valid0) {
            if (data.protocol_version !== void 0) {
              const _errs4 = errors;
              if (1 !== data.protocol_version) {
                validate23.errors = [{ instancePath: instancePath + "/protocol_version", schemaPath: "#/properties/protocol_version/const", keyword: "const", params: { allowedValue: 1 }, message: "must be equal to constant" }];
                return false;
              }
              var valid0 = _errs4 === errors;
            } else {
              var valid0 = true;
            }
            if (valid0) {
              if (data.record_type !== void 0) {
                const _errs5 = errors;
                if ("service_identity_key_rotation" !== data.record_type) {
                  validate23.errors = [{ instancePath: instancePath + "/record_type", schemaPath: "#/properties/record_type/const", keyword: "const", params: { allowedValue: "service_identity_key_rotation" }, message: "must be equal to constant" }];
                  return false;
                }
                var valid0 = _errs5 === errors;
              } else {
                var valid0 = true;
              }
              if (valid0) {
                if (data.onion !== void 0) {
                  let data3 = data.onion;
                  const _errs6 = errors;
                  if (errors === _errs6) {
                    if (typeof data3 === "string") {
                      if (func2(data3) > 62) {
                        validate23.errors = [{ instancePath: instancePath + "/onion", schemaPath: "#/properties/onion/maxLength", keyword: "maxLength", params: { limit: 62 }, message: "must NOT have more than 62 characters" }];
                        return false;
                      } else {
                        if (func2(data3) < 62) {
                          validate23.errors = [{ instancePath: instancePath + "/onion", schemaPath: "#/properties/onion/minLength", keyword: "minLength", params: { limit: 62 }, message: "must NOT have fewer than 62 characters" }];
                          return false;
                        } else {
                          if (!pattern5.test(data3)) {
                            validate23.errors = [{ instancePath: instancePath + "/onion", schemaPath: "#/properties/onion/pattern", keyword: "pattern", params: { pattern: "^[a-z2-7]{56}\\.onion$" }, message: 'must match pattern "^[a-z2-7]{56}\\.onion$"' }];
                            return false;
                          }
                        }
                      }
                    } else {
                      validate23.errors = [{ instancePath: instancePath + "/onion", schemaPath: "#/properties/onion/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                      return false;
                    }
                  }
                  var valid0 = _errs6 === errors;
                } else {
                  var valid0 = true;
                }
                if (valid0) {
                  if (data.old_public_key !== void 0) {
                    let data4 = data.old_public_key;
                    const _errs8 = errors;
                    if (errors === _errs8) {
                      if (typeof data4 === "string") {
                        if (func2(data4) > 51) {
                          validate23.errors = [{ instancePath: instancePath + "/old_public_key", schemaPath: "#/properties/old_public_key/maxLength", keyword: "maxLength", params: { limit: 51 }, message: "must NOT have more than 51 characters" }];
                          return false;
                        } else {
                          if (func2(data4) < 51) {
                            validate23.errors = [{ instancePath: instancePath + "/old_public_key", schemaPath: "#/properties/old_public_key/minLength", keyword: "minLength", params: { limit: 51 }, message: "must NOT have fewer than 51 characters" }];
                            return false;
                          } else {
                            if (!pattern4.test(data4)) {
                              validate23.errors = [{ instancePath: instancePath + "/old_public_key", schemaPath: "#/properties/old_public_key/pattern", keyword: "pattern", params: { pattern: "^ed25519:[A-Za-z0-9_-]{43}$" }, message: 'must match pattern "^ed25519:[A-Za-z0-9_-]{43}$"' }];
                              return false;
                            }
                          }
                        }
                      } else {
                        validate23.errors = [{ instancePath: instancePath + "/old_public_key", schemaPath: "#/properties/old_public_key/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                        return false;
                      }
                    }
                    var valid0 = _errs8 === errors;
                  } else {
                    var valid0 = true;
                  }
                  if (valid0) {
                    if (data.new_public_key !== void 0) {
                      let data5 = data.new_public_key;
                      const _errs10 = errors;
                      if (errors === _errs10) {
                        if (typeof data5 === "string") {
                          if (func2(data5) > 51) {
                            validate23.errors = [{ instancePath: instancePath + "/new_public_key", schemaPath: "#/properties/new_public_key/maxLength", keyword: "maxLength", params: { limit: 51 }, message: "must NOT have more than 51 characters" }];
                            return false;
                          } else {
                            if (func2(data5) < 51) {
                              validate23.errors = [{ instancePath: instancePath + "/new_public_key", schemaPath: "#/properties/new_public_key/minLength", keyword: "minLength", params: { limit: 51 }, message: "must NOT have fewer than 51 characters" }];
                              return false;
                            } else {
                              if (!pattern4.test(data5)) {
                                validate23.errors = [{ instancePath: instancePath + "/new_public_key", schemaPath: "#/properties/new_public_key/pattern", keyword: "pattern", params: { pattern: "^ed25519:[A-Za-z0-9_-]{43}$" }, message: 'must match pattern "^ed25519:[A-Za-z0-9_-]{43}$"' }];
                                return false;
                              }
                            }
                          }
                        } else {
                          validate23.errors = [{ instancePath: instancePath + "/new_public_key", schemaPath: "#/properties/new_public_key/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                          return false;
                        }
                      }
                      var valid0 = _errs10 === errors;
                    } else {
                      var valid0 = true;
                    }
                    if (valid0) {
                      if (data.effective_revision !== void 0) {
                        let data6 = data.effective_revision;
                        const _errs12 = errors;
                        if (!(typeof data6 == "number" && (!(data6 % 1) && !isNaN(data6)) && isFinite(data6))) {
                          validate23.errors = [{ instancePath: instancePath + "/effective_revision", schemaPath: "#/properties/effective_revision/type", keyword: "type", params: { type: "integer" }, message: "must be integer" }];
                          return false;
                        }
                        if (errors === _errs12) {
                          if (typeof data6 == "number" && isFinite(data6)) {
                            if (data6 > 9007199254740991 || isNaN(data6)) {
                              validate23.errors = [{ instancePath: instancePath + "/effective_revision", schemaPath: "#/properties/effective_revision/maximum", keyword: "maximum", params: { comparison: "<=", limit: 9007199254740991 }, message: "must be <= 9007199254740991" }];
                              return false;
                            } else {
                              if (data6 < 1 || isNaN(data6)) {
                                validate23.errors = [{ instancePath: instancePath + "/effective_revision", schemaPath: "#/properties/effective_revision/minimum", keyword: "minimum", params: { comparison: ">=", limit: 1 }, message: "must be >= 1" }];
                                return false;
                              }
                            }
                          }
                        }
                        var valid0 = _errs12 === errors;
                      } else {
                        var valid0 = true;
                      }
                      if (valid0) {
                        if (data.previous_state_hash !== void 0) {
                          let data7 = data.previous_state_hash;
                          const _errs14 = errors;
                          if (errors === _errs14) {
                            if (typeof data7 === "string") {
                              if (func2(data7) > 71) {
                                validate23.errors = [{ instancePath: instancePath + "/previous_state_hash", schemaPath: "#/properties/previous_state_hash/maxLength", keyword: "maxLength", params: { limit: 71 }, message: "must NOT have more than 71 characters" }];
                                return false;
                              } else {
                                if (func2(data7) < 71) {
                                  validate23.errors = [{ instancePath: instancePath + "/previous_state_hash", schemaPath: "#/properties/previous_state_hash/minLength", keyword: "minLength", params: { limit: 71 }, message: "must NOT have fewer than 71 characters" }];
                                  return false;
                                } else {
                                  if (!pattern7.test(data7)) {
                                    validate23.errors = [{ instancePath: instancePath + "/previous_state_hash", schemaPath: "#/properties/previous_state_hash/pattern", keyword: "pattern", params: { pattern: "^sha256:[0-9a-f]{64}$" }, message: 'must match pattern "^sha256:[0-9a-f]{64}$"' }];
                                    return false;
                                  }
                                }
                              }
                            } else {
                              validate23.errors = [{ instancePath: instancePath + "/previous_state_hash", schemaPath: "#/properties/previous_state_hash/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                              return false;
                            }
                          }
                          var valid0 = _errs14 === errors;
                        } else {
                          var valid0 = true;
                        }
                        if (valid0) {
                          if (data.new_key_confirmation !== void 0) {
                            let data8 = data.new_key_confirmation;
                            const _errs16 = errors;
                            if (errors === _errs16) {
                              if (typeof data8 === "string") {
                                if (func2(data8) > 97) {
                                  validate23.errors = [{ instancePath: instancePath + "/new_key_confirmation", schemaPath: "#/properties/new_key_confirmation/maxLength", keyword: "maxLength", params: { limit: 97 }, message: "must NOT have more than 97 characters" }];
                                  return false;
                                } else {
                                  if (func2(data8) < 97) {
                                    validate23.errors = [{ instancePath: instancePath + "/new_key_confirmation", schemaPath: "#/properties/new_key_confirmation/minLength", keyword: "minLength", params: { limit: 97 }, message: "must NOT have fewer than 97 characters" }];
                                    return false;
                                  } else {
                                    if (!pattern9.test(data8)) {
                                      validate23.errors = [{ instancePath: instancePath + "/new_key_confirmation", schemaPath: "#/properties/new_key_confirmation/pattern", keyword: "pattern", params: { pattern: "^ed25519sig:[A-Za-z0-9_-]{86}$" }, message: 'must match pattern "^ed25519sig:[A-Za-z0-9_-]{86}$"' }];
                                      return false;
                                    }
                                  }
                                }
                              } else {
                                validate23.errors = [{ instancePath: instancePath + "/new_key_confirmation", schemaPath: "#/properties/new_key_confirmation/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                return false;
                              }
                            }
                            var valid0 = _errs16 === errors;
                          } else {
                            var valid0 = true;
                          }
                          if (valid0) {
                            if (data.old_key_signature !== void 0) {
                              let data9 = data.old_key_signature;
                              const _errs18 = errors;
                              if (errors === _errs18) {
                                if (typeof data9 === "string") {
                                  if (func2(data9) > 97) {
                                    validate23.errors = [{ instancePath: instancePath + "/old_key_signature", schemaPath: "#/properties/old_key_signature/maxLength", keyword: "maxLength", params: { limit: 97 }, message: "must NOT have more than 97 characters" }];
                                    return false;
                                  } else {
                                    if (func2(data9) < 97) {
                                      validate23.errors = [{ instancePath: instancePath + "/old_key_signature", schemaPath: "#/properties/old_key_signature/minLength", keyword: "minLength", params: { limit: 97 }, message: "must NOT have fewer than 97 characters" }];
                                      return false;
                                    } else {
                                      if (!pattern9.test(data9)) {
                                        validate23.errors = [{ instancePath: instancePath + "/old_key_signature", schemaPath: "#/properties/old_key_signature/pattern", keyword: "pattern", params: { pattern: "^ed25519sig:[A-Za-z0-9_-]{86}$" }, message: 'must match pattern "^ed25519sig:[A-Za-z0-9_-]{86}$"' }];
                                        return false;
                                      }
                                    }
                                  }
                                } else {
                                  validate23.errors = [{ instancePath: instancePath + "/old_key_signature", schemaPath: "#/properties/old_key_signature/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                  return false;
                                }
                              }
                              var valid0 = _errs18 === errors;
                            } else {
                              var valid0 = true;
                            }
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    } else {
      validate23.errors = [{ instancePath, schemaPath: "#/type", keyword: "type", params: { type: "object" }, message: "must be object" }];
      return false;
    }
  }
  validate23.errors = vErrors;
  return errors === 0;
}
validate23.evaluated = { "props": true, "dynamicProps": false, "dynamicItems": false };
var service_manifest = validate24;
var schema35 = { "type": "object", "properties": { "protocol": { "const": "Onion Service Discovery" }, "protocol_version": { "const": 1 }, "record_type": { "const": "service_manifest" }, "onion": { "type": "string", "minLength": 62, "maxLength": 62, "pattern": "^[a-z2-7]{56}\\.onion$" }, "service_identity_public_key": { "type": "string", "minLength": 51, "maxLength": 51, "pattern": "^ed25519:[A-Za-z0-9_-]{43}$" }, "revision": { "type": "integer", "minimum": 1, "maximum": 9007199254740991 }, "previous_manifest_hash": { "anyOf": [{ "type": "string", "minLength": 71, "maxLength": 71, "pattern": "^sha256:[0-9a-f]{64}$" }, { "type": "null" }] }, "discovery": { "type": "object", "properties": { "publish_service": { "type": "boolean" }, "mirror_directory": { "type": "boolean" }, "discover_services": { "type": "boolean" }, "sync_with_peers": { "type": "boolean" } }, "required": ["publish_service", "mirror_directory", "discover_services", "sync_with_peers"], "additionalProperties": false }, "service": { "type": "object", "properties": { "name": { "type": "string", "minLength": 0, "maxLength": 128, "pattern": "^[^\0-\x7F-\x9F]*$" }, "description": { "type": "string", "minLength": 0, "maxLength": 2048, "pattern": "^[^\0-\x7F-\x9F]*$" }, "languages": { "type": "array", "items": { "type": "string", "minLength": 2, "maxLength": 35, "pattern": "^[a-z]{2,8}(?:-[a-z0-9]{1,8})*$" }, "maxItems": 16, "uniqueItems": true }, "categories": { "type": "array", "items": { "type": "string", "minLength": 1, "maxLength": 64, "pattern": "^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$" }, "maxItems": 32, "uniqueItems": true } }, "required": ["name", "languages", "categories"], "additionalProperties": false }, "links": { "type": "object", "properties": { "related_onion_services": { "type": "array", "items": { "type": "string", "minLength": 62, "maxLength": 62, "pattern": "^[a-z2-7]{56}\\.onion$" }, "maxItems": 32, "uniqueItems": true }, "clearnet": { "type": "array", "items": { "type": "string", "minLength": 8, "maxLength": 2048, "pattern": "^https://[^/@\\s]+(?:/[^\\s]*)?$" }, "maxItems": 8, "uniqueItems": true } }, "required": [], "additionalProperties": false }, "published_at": { "type": "string", "minLength": 20, "maxLength": 20, "pattern": "^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$" }, "signature": { "type": "string", "minLength": 97, "maxLength": 97, "pattern": "^ed25519sig:[A-Za-z0-9_-]{86}$" } }, "required": ["protocol", "protocol_version", "record_type", "onion", "service_identity_public_key", "revision", "previous_manifest_hash", "discovery", "service", "signature"], "additionalProperties": false, "$schema": "https://json-schema.org/draft/2020-12/schema", "$id": "https://github.com/ShawnJeromeMcCray/Onion-Service-Discovery/schemas/draft-v1/service_manifest.json", "title": "Onion Service Discovery service_manifest", "$comment": "Implementation draft; protocol freeze and human crypto approval are outstanding.", "allOf": [{ "if": { "properties": { "revision": { "const": 1 } } }, "then": { "properties": { "previous_manifest_hash": { "type": "null" } } }, "else": { "properties": { "previous_manifest_hash": { "type": "string", "minLength": 71, "maxLength": 71, "pattern": "^sha256:[0-9a-f]{64}$" } } } }, { "if": { "properties": { "discovery": { "properties": { "publish_service": { "const": true } } } } }, "then": { "properties": { "service": { "properties": { "name": { "minLength": 1 } } } } } }] };
var pattern35 = new RegExp("^[a-z]{2,8}(?:-[a-z0-9]{1,8})*$", "u");
var pattern36 = new RegExp("^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$", "u");
var pattern38 = new RegExp("^https://[^/@\\s]+(?:/[^\\s]*)?$", "u");
function validate24(data, { instancePath = "", parentData, parentDataProperty, rootData = data, dynamicAnchors = {} } = {}) {
  ;
  let vErrors = null;
  let errors = 0;
  const evaluated0 = validate24.evaluated;
  if (evaluated0.dynamicProps) {
    evaluated0.props = void 0;
  }
  if (evaluated0.dynamicItems) {
    evaluated0.items = void 0;
  }
  const _errs2 = errors;
  const _errs3 = errors;
  let valid1 = true;
  const _errs4 = errors;
  if (data && typeof data == "object" && !Array.isArray(data)) {
    if (data.revision !== void 0) {
      if (1 !== data.revision) {
        const err0 = {};
        if (vErrors === null) {
          vErrors = [err0];
        } else {
          vErrors.push(err0);
        }
        errors++;
      }
    }
  }
  var _valid0 = _errs4 === errors;
  errors = _errs3;
  if (vErrors !== null) {
    if (_errs3) {
      vErrors.length = _errs3;
    } else {
      vErrors = null;
    }
  }
  let ifClause0;
  if (_valid0) {
    const _errs6 = errors;
    if (data && typeof data == "object" && !Array.isArray(data)) {
      if (data.previous_manifest_hash !== void 0) {
        if (data.previous_manifest_hash !== null) {
          validate24.errors = [{ instancePath: instancePath + "/previous_manifest_hash", schemaPath: "#/allOf/0/then/properties/previous_manifest_hash/type", keyword: "type", params: { type: "null" }, message: "must be null" }];
          return false;
        }
      }
    }
    var _valid0 = _errs6 === errors;
    valid1 = _valid0;
    if (valid1) {
      var props0 = {};
      props0.previous_manifest_hash = true;
      props0.revision = true;
    }
    ifClause0 = "then";
  } else {
    const _errs9 = errors;
    if (data && typeof data == "object" && !Array.isArray(data)) {
      if (data.previous_manifest_hash !== void 0) {
        let data2 = data.previous_manifest_hash;
        const _errs10 = errors;
        if (errors === _errs10) {
          if (typeof data2 === "string") {
            if (func2(data2) > 71) {
              validate24.errors = [{ instancePath: instancePath + "/previous_manifest_hash", schemaPath: "#/allOf/0/else/properties/previous_manifest_hash/maxLength", keyword: "maxLength", params: { limit: 71 }, message: "must NOT have more than 71 characters" }];
              return false;
            } else {
              if (func2(data2) < 71) {
                validate24.errors = [{ instancePath: instancePath + "/previous_manifest_hash", schemaPath: "#/allOf/0/else/properties/previous_manifest_hash/minLength", keyword: "minLength", params: { limit: 71 }, message: "must NOT have fewer than 71 characters" }];
                return false;
              } else {
                if (!pattern7.test(data2)) {
                  validate24.errors = [{ instancePath: instancePath + "/previous_manifest_hash", schemaPath: "#/allOf/0/else/properties/previous_manifest_hash/pattern", keyword: "pattern", params: { pattern: "^sha256:[0-9a-f]{64}$" }, message: 'must match pattern "^sha256:[0-9a-f]{64}$"' }];
                  return false;
                }
              }
            }
          } else {
            validate24.errors = [{ instancePath: instancePath + "/previous_manifest_hash", schemaPath: "#/allOf/0/else/properties/previous_manifest_hash/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
            return false;
          }
        }
      }
    }
    var _valid0 = _errs9 === errors;
    valid1 = _valid0;
    if (valid1) {
      if (props0 !== true) {
        props0 = props0 || {};
        props0.previous_manifest_hash = true;
      }
    }
    ifClause0 = "else";
  }
  if (!valid1) {
    const err1 = { instancePath, schemaPath: "#/allOf/0/if", keyword: "if", params: { failingKeyword: ifClause0 }, message: 'must match "' + ifClause0 + '" schema' };
    if (vErrors === null) {
      vErrors = [err1];
    } else {
      vErrors.push(err1);
    }
    errors++;
    validate24.errors = vErrors;
    return false;
  }
  var valid0 = _errs2 === errors;
  if (valid0) {
    const _errs12 = errors;
    const _errs13 = errors;
    let valid5 = true;
    const _errs14 = errors;
    if (data && typeof data == "object" && !Array.isArray(data)) {
      if (data.discovery !== void 0) {
        let data3 = data.discovery;
        if (data3 && typeof data3 == "object" && !Array.isArray(data3)) {
          if (data3.publish_service !== void 0) {
            if (true !== data3.publish_service) {
              const err2 = {};
              if (vErrors === null) {
                vErrors = [err2];
              } else {
                vErrors.push(err2);
              }
              errors++;
            }
          }
        }
      }
    }
    var _valid1 = _errs14 === errors;
    errors = _errs13;
    if (vErrors !== null) {
      if (_errs13) {
        vErrors.length = _errs13;
      } else {
        vErrors = null;
      }
    }
    if (_valid1) {
      const _errs17 = errors;
      if (data && typeof data == "object" && !Array.isArray(data)) {
        if (data.service !== void 0) {
          let data5 = data.service;
          if (data5 && typeof data5 == "object" && !Array.isArray(data5)) {
            if (data5.name !== void 0) {
              let data6 = data5.name;
              if (typeof data6 === "string") {
                if (func2(data6) < 1) {
                  validate24.errors = [{ instancePath: instancePath + "/service/name", schemaPath: "#/allOf/1/then/properties/service/properties/name/minLength", keyword: "minLength", params: { limit: 1 }, message: "must NOT have fewer than 1 characters" }];
                  return false;
                }
              }
            }
          }
        }
      }
      var _valid1 = _errs17 === errors;
      valid5 = _valid1;
      if (valid5) {
        var props1 = {};
        props1.service = true;
        props1.discovery = true;
      }
    }
    if (!valid5) {
      const err3 = { instancePath, schemaPath: "#/allOf/1/if", keyword: "if", params: { failingKeyword: "then" }, message: 'must match "then" schema' };
      if (vErrors === null) {
        vErrors = [err3];
      } else {
        vErrors.push(err3);
      }
      errors++;
      validate24.errors = vErrors;
      return false;
    }
    var valid0 = _errs12 === errors;
    if (valid0) {
      if (props0 !== true && props1 !== void 0) {
        if (props1 === true) {
          props0 = true;
        } else {
          props0 = props0 || {};
          Object.assign(props0, props1);
        }
      }
    }
  }
  if (errors === 0) {
    if (data && typeof data == "object" && !Array.isArray(data)) {
      let missing0;
      if (data.protocol === void 0 && (missing0 = "protocol") || data.protocol_version === void 0 && (missing0 = "protocol_version") || data.record_type === void 0 && (missing0 = "record_type") || data.onion === void 0 && (missing0 = "onion") || data.service_identity_public_key === void 0 && (missing0 = "service_identity_public_key") || data.revision === void 0 && (missing0 = "revision") || data.previous_manifest_hash === void 0 && (missing0 = "previous_manifest_hash") || data.discovery === void 0 && (missing0 = "discovery") || data.service === void 0 && (missing0 = "service") || data.signature === void 0 && (missing0 = "signature")) {
        validate24.errors = [{ instancePath, schemaPath: "#/required", keyword: "required", params: { missingProperty: missing0 }, message: "must have required property '" + missing0 + "'" }];
        return false;
      } else {
        const _errs20 = errors;
        for (const key0 in data) {
          if (!func1.call(schema35.properties, key0)) {
            validate24.errors = [{ instancePath, schemaPath: "#/additionalProperties", keyword: "additionalProperties", params: { additionalProperty: key0 }, message: "must NOT have additional properties" }];
            return false;
            break;
          }
        }
        if (_errs20 === errors) {
          if (data.protocol !== void 0) {
            const _errs21 = errors;
            if ("Onion Service Discovery" !== data.protocol) {
              validate24.errors = [{ instancePath: instancePath + "/protocol", schemaPath: "#/properties/protocol/const", keyword: "const", params: { allowedValue: "Onion Service Discovery" }, message: "must be equal to constant" }];
              return false;
            }
            var valid10 = _errs21 === errors;
          } else {
            var valid10 = true;
          }
          if (valid10) {
            if (data.protocol_version !== void 0) {
              const _errs22 = errors;
              if (1 !== data.protocol_version) {
                validate24.errors = [{ instancePath: instancePath + "/protocol_version", schemaPath: "#/properties/protocol_version/const", keyword: "const", params: { allowedValue: 1 }, message: "must be equal to constant" }];
                return false;
              }
              var valid10 = _errs22 === errors;
            } else {
              var valid10 = true;
            }
            if (valid10) {
              if (data.record_type !== void 0) {
                const _errs23 = errors;
                if ("service_manifest" !== data.record_type) {
                  validate24.errors = [{ instancePath: instancePath + "/record_type", schemaPath: "#/properties/record_type/const", keyword: "const", params: { allowedValue: "service_manifest" }, message: "must be equal to constant" }];
                  return false;
                }
                var valid10 = _errs23 === errors;
              } else {
                var valid10 = true;
              }
              if (valid10) {
                if (data.onion !== void 0) {
                  let data10 = data.onion;
                  const _errs24 = errors;
                  if (errors === _errs24) {
                    if (typeof data10 === "string") {
                      if (func2(data10) > 62) {
                        validate24.errors = [{ instancePath: instancePath + "/onion", schemaPath: "#/properties/onion/maxLength", keyword: "maxLength", params: { limit: 62 }, message: "must NOT have more than 62 characters" }];
                        return false;
                      } else {
                        if (func2(data10) < 62) {
                          validate24.errors = [{ instancePath: instancePath + "/onion", schemaPath: "#/properties/onion/minLength", keyword: "minLength", params: { limit: 62 }, message: "must NOT have fewer than 62 characters" }];
                          return false;
                        } else {
                          if (!pattern5.test(data10)) {
                            validate24.errors = [{ instancePath: instancePath + "/onion", schemaPath: "#/properties/onion/pattern", keyword: "pattern", params: { pattern: "^[a-z2-7]{56}\\.onion$" }, message: 'must match pattern "^[a-z2-7]{56}\\.onion$"' }];
                            return false;
                          }
                        }
                      }
                    } else {
                      validate24.errors = [{ instancePath: instancePath + "/onion", schemaPath: "#/properties/onion/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                      return false;
                    }
                  }
                  var valid10 = _errs24 === errors;
                } else {
                  var valid10 = true;
                }
                if (valid10) {
                  if (data.service_identity_public_key !== void 0) {
                    let data11 = data.service_identity_public_key;
                    const _errs26 = errors;
                    if (errors === _errs26) {
                      if (typeof data11 === "string") {
                        if (func2(data11) > 51) {
                          validate24.errors = [{ instancePath: instancePath + "/service_identity_public_key", schemaPath: "#/properties/service_identity_public_key/maxLength", keyword: "maxLength", params: { limit: 51 }, message: "must NOT have more than 51 characters" }];
                          return false;
                        } else {
                          if (func2(data11) < 51) {
                            validate24.errors = [{ instancePath: instancePath + "/service_identity_public_key", schemaPath: "#/properties/service_identity_public_key/minLength", keyword: "minLength", params: { limit: 51 }, message: "must NOT have fewer than 51 characters" }];
                            return false;
                          } else {
                            if (!pattern4.test(data11)) {
                              validate24.errors = [{ instancePath: instancePath + "/service_identity_public_key", schemaPath: "#/properties/service_identity_public_key/pattern", keyword: "pattern", params: { pattern: "^ed25519:[A-Za-z0-9_-]{43}$" }, message: 'must match pattern "^ed25519:[A-Za-z0-9_-]{43}$"' }];
                              return false;
                            }
                          }
                        }
                      } else {
                        validate24.errors = [{ instancePath: instancePath + "/service_identity_public_key", schemaPath: "#/properties/service_identity_public_key/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                        return false;
                      }
                    }
                    var valid10 = _errs26 === errors;
                  } else {
                    var valid10 = true;
                  }
                  if (valid10) {
                    if (data.revision !== void 0) {
                      let data12 = data.revision;
                      const _errs28 = errors;
                      if (!(typeof data12 == "number" && (!(data12 % 1) && !isNaN(data12)) && isFinite(data12))) {
                        validate24.errors = [{ instancePath: instancePath + "/revision", schemaPath: "#/properties/revision/type", keyword: "type", params: { type: "integer" }, message: "must be integer" }];
                        return false;
                      }
                      if (errors === _errs28) {
                        if (typeof data12 == "number" && isFinite(data12)) {
                          if (data12 > 9007199254740991 || isNaN(data12)) {
                            validate24.errors = [{ instancePath: instancePath + "/revision", schemaPath: "#/properties/revision/maximum", keyword: "maximum", params: { comparison: "<=", limit: 9007199254740991 }, message: "must be <= 9007199254740991" }];
                            return false;
                          } else {
                            if (data12 < 1 || isNaN(data12)) {
                              validate24.errors = [{ instancePath: instancePath + "/revision", schemaPath: "#/properties/revision/minimum", keyword: "minimum", params: { comparison: ">=", limit: 1 }, message: "must be >= 1" }];
                              return false;
                            }
                          }
                        }
                      }
                      var valid10 = _errs28 === errors;
                    } else {
                      var valid10 = true;
                    }
                    if (valid10) {
                      if (data.previous_manifest_hash !== void 0) {
                        let data13 = data.previous_manifest_hash;
                        const _errs30 = errors;
                        const _errs31 = errors;
                        let valid11 = false;
                        const _errs32 = errors;
                        if (errors === _errs32) {
                          if (typeof data13 === "string") {
                            if (func2(data13) > 71) {
                              const err4 = { instancePath: instancePath + "/previous_manifest_hash", schemaPath: "#/properties/previous_manifest_hash/anyOf/0/maxLength", keyword: "maxLength", params: { limit: 71 }, message: "must NOT have more than 71 characters" };
                              if (vErrors === null) {
                                vErrors = [err4];
                              } else {
                                vErrors.push(err4);
                              }
                              errors++;
                            } else {
                              if (func2(data13) < 71) {
                                const err5 = { instancePath: instancePath + "/previous_manifest_hash", schemaPath: "#/properties/previous_manifest_hash/anyOf/0/minLength", keyword: "minLength", params: { limit: 71 }, message: "must NOT have fewer than 71 characters" };
                                if (vErrors === null) {
                                  vErrors = [err5];
                                } else {
                                  vErrors.push(err5);
                                }
                                errors++;
                              } else {
                                if (!pattern7.test(data13)) {
                                  const err6 = { instancePath: instancePath + "/previous_manifest_hash", schemaPath: "#/properties/previous_manifest_hash/anyOf/0/pattern", keyword: "pattern", params: { pattern: "^sha256:[0-9a-f]{64}$" }, message: 'must match pattern "^sha256:[0-9a-f]{64}$"' };
                                  if (vErrors === null) {
                                    vErrors = [err6];
                                  } else {
                                    vErrors.push(err6);
                                  }
                                  errors++;
                                }
                              }
                            }
                          } else {
                            const err7 = { instancePath: instancePath + "/previous_manifest_hash", schemaPath: "#/properties/previous_manifest_hash/anyOf/0/type", keyword: "type", params: { type: "string" }, message: "must be string" };
                            if (vErrors === null) {
                              vErrors = [err7];
                            } else {
                              vErrors.push(err7);
                            }
                            errors++;
                          }
                        }
                        var _valid2 = _errs32 === errors;
                        valid11 = valid11 || _valid2;
                        const _errs34 = errors;
                        if (data13 !== null) {
                          const err8 = { instancePath: instancePath + "/previous_manifest_hash", schemaPath: "#/properties/previous_manifest_hash/anyOf/1/type", keyword: "type", params: { type: "null" }, message: "must be null" };
                          if (vErrors === null) {
                            vErrors = [err8];
                          } else {
                            vErrors.push(err8);
                          }
                          errors++;
                        }
                        var _valid2 = _errs34 === errors;
                        valid11 = valid11 || _valid2;
                        if (!valid11) {
                          const err9 = { instancePath: instancePath + "/previous_manifest_hash", schemaPath: "#/properties/previous_manifest_hash/anyOf", keyword: "anyOf", params: {}, message: "must match a schema in anyOf" };
                          if (vErrors === null) {
                            vErrors = [err9];
                          } else {
                            vErrors.push(err9);
                          }
                          errors++;
                          validate24.errors = vErrors;
                          return false;
                        } else {
                          errors = _errs31;
                          if (vErrors !== null) {
                            if (_errs31) {
                              vErrors.length = _errs31;
                            } else {
                              vErrors = null;
                            }
                          }
                        }
                        var valid10 = _errs30 === errors;
                      } else {
                        var valid10 = true;
                      }
                      if (valid10) {
                        if (data.discovery !== void 0) {
                          let data14 = data.discovery;
                          const _errs36 = errors;
                          if (errors === _errs36) {
                            if (data14 && typeof data14 == "object" && !Array.isArray(data14)) {
                              let missing1;
                              if (data14.publish_service === void 0 && (missing1 = "publish_service") || data14.mirror_directory === void 0 && (missing1 = "mirror_directory") || data14.discover_services === void 0 && (missing1 = "discover_services") || data14.sync_with_peers === void 0 && (missing1 = "sync_with_peers")) {
                                validate24.errors = [{ instancePath: instancePath + "/discovery", schemaPath: "#/properties/discovery/required", keyword: "required", params: { missingProperty: missing1 }, message: "must have required property '" + missing1 + "'" }];
                                return false;
                              } else {
                                const _errs38 = errors;
                                for (const key1 in data14) {
                                  if (!(key1 === "publish_service" || key1 === "mirror_directory" || key1 === "discover_services" || key1 === "sync_with_peers")) {
                                    validate24.errors = [{ instancePath: instancePath + "/discovery", schemaPath: "#/properties/discovery/additionalProperties", keyword: "additionalProperties", params: { additionalProperty: key1 }, message: "must NOT have additional properties" }];
                                    return false;
                                    break;
                                  }
                                }
                                if (_errs38 === errors) {
                                  if (data14.publish_service !== void 0) {
                                    const _errs39 = errors;
                                    if (typeof data14.publish_service !== "boolean") {
                                      validate24.errors = [{ instancePath: instancePath + "/discovery/publish_service", schemaPath: "#/properties/discovery/properties/publish_service/type", keyword: "type", params: { type: "boolean" }, message: "must be boolean" }];
                                      return false;
                                    }
                                    var valid12 = _errs39 === errors;
                                  } else {
                                    var valid12 = true;
                                  }
                                  if (valid12) {
                                    if (data14.mirror_directory !== void 0) {
                                      const _errs41 = errors;
                                      if (typeof data14.mirror_directory !== "boolean") {
                                        validate24.errors = [{ instancePath: instancePath + "/discovery/mirror_directory", schemaPath: "#/properties/discovery/properties/mirror_directory/type", keyword: "type", params: { type: "boolean" }, message: "must be boolean" }];
                                        return false;
                                      }
                                      var valid12 = _errs41 === errors;
                                    } else {
                                      var valid12 = true;
                                    }
                                    if (valid12) {
                                      if (data14.discover_services !== void 0) {
                                        const _errs43 = errors;
                                        if (typeof data14.discover_services !== "boolean") {
                                          validate24.errors = [{ instancePath: instancePath + "/discovery/discover_services", schemaPath: "#/properties/discovery/properties/discover_services/type", keyword: "type", params: { type: "boolean" }, message: "must be boolean" }];
                                          return false;
                                        }
                                        var valid12 = _errs43 === errors;
                                      } else {
                                        var valid12 = true;
                                      }
                                      if (valid12) {
                                        if (data14.sync_with_peers !== void 0) {
                                          const _errs45 = errors;
                                          if (typeof data14.sync_with_peers !== "boolean") {
                                            validate24.errors = [{ instancePath: instancePath + "/discovery/sync_with_peers", schemaPath: "#/properties/discovery/properties/sync_with_peers/type", keyword: "type", params: { type: "boolean" }, message: "must be boolean" }];
                                            return false;
                                          }
                                          var valid12 = _errs45 === errors;
                                        } else {
                                          var valid12 = true;
                                        }
                                      }
                                    }
                                  }
                                }
                              }
                            } else {
                              validate24.errors = [{ instancePath: instancePath + "/discovery", schemaPath: "#/properties/discovery/type", keyword: "type", params: { type: "object" }, message: "must be object" }];
                              return false;
                            }
                          }
                          var valid10 = _errs36 === errors;
                        } else {
                          var valid10 = true;
                        }
                        if (valid10) {
                          if (data.service !== void 0) {
                            let data19 = data.service;
                            const _errs47 = errors;
                            if (errors === _errs47) {
                              if (data19 && typeof data19 == "object" && !Array.isArray(data19)) {
                                let missing2;
                                if (data19.name === void 0 && (missing2 = "name") || data19.languages === void 0 && (missing2 = "languages") || data19.categories === void 0 && (missing2 = "categories")) {
                                  validate24.errors = [{ instancePath: instancePath + "/service", schemaPath: "#/properties/service/required", keyword: "required", params: { missingProperty: missing2 }, message: "must have required property '" + missing2 + "'" }];
                                  return false;
                                } else {
                                  const _errs49 = errors;
                                  for (const key2 in data19) {
                                    if (!(key2 === "name" || key2 === "description" || key2 === "languages" || key2 === "categories")) {
                                      validate24.errors = [{ instancePath: instancePath + "/service", schemaPath: "#/properties/service/additionalProperties", keyword: "additionalProperties", params: { additionalProperty: key2 }, message: "must NOT have additional properties" }];
                                      return false;
                                      break;
                                    }
                                  }
                                  if (_errs49 === errors) {
                                    if (data19.name !== void 0) {
                                      let data20 = data19.name;
                                      const _errs50 = errors;
                                      if (errors === _errs50) {
                                        if (typeof data20 === "string") {
                                          if (func2(data20) > 128) {
                                            validate24.errors = [{ instancePath: instancePath + "/service/name", schemaPath: "#/properties/service/properties/name/maxLength", keyword: "maxLength", params: { limit: 128 }, message: "must NOT have more than 128 characters" }];
                                            return false;
                                          } else {
                                            if (func2(data20) < 0) {
                                              validate24.errors = [{ instancePath: instancePath + "/service/name", schemaPath: "#/properties/service/properties/name/minLength", keyword: "minLength", params: { limit: 0 }, message: "must NOT have fewer than 0 characters" }];
                                              return false;
                                            } else {
                                              if (!pattern15.test(data20)) {
                                                validate24.errors = [{ instancePath: instancePath + "/service/name", schemaPath: "#/properties/service/properties/name/pattern", keyword: "pattern", params: { pattern: "^[^\0-\x7F-\x9F]*$" }, message: 'must match pattern "^[^\0-\x7F-\x9F]*$"' }];
                                                return false;
                                              }
                                            }
                                          }
                                        } else {
                                          validate24.errors = [{ instancePath: instancePath + "/service/name", schemaPath: "#/properties/service/properties/name/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                          return false;
                                        }
                                      }
                                      var valid13 = _errs50 === errors;
                                    } else {
                                      var valid13 = true;
                                    }
                                    if (valid13) {
                                      if (data19.description !== void 0) {
                                        let data21 = data19.description;
                                        const _errs52 = errors;
                                        if (errors === _errs52) {
                                          if (typeof data21 === "string") {
                                            if (func2(data21) > 2048) {
                                              validate24.errors = [{ instancePath: instancePath + "/service/description", schemaPath: "#/properties/service/properties/description/maxLength", keyword: "maxLength", params: { limit: 2048 }, message: "must NOT have more than 2048 characters" }];
                                              return false;
                                            } else {
                                              if (func2(data21) < 0) {
                                                validate24.errors = [{ instancePath: instancePath + "/service/description", schemaPath: "#/properties/service/properties/description/minLength", keyword: "minLength", params: { limit: 0 }, message: "must NOT have fewer than 0 characters" }];
                                                return false;
                                              } else {
                                                if (!pattern15.test(data21)) {
                                                  validate24.errors = [{ instancePath: instancePath + "/service/description", schemaPath: "#/properties/service/properties/description/pattern", keyword: "pattern", params: { pattern: "^[^\0-\x7F-\x9F]*$" }, message: 'must match pattern "^[^\0-\x7F-\x9F]*$"' }];
                                                  return false;
                                                }
                                              }
                                            }
                                          } else {
                                            validate24.errors = [{ instancePath: instancePath + "/service/description", schemaPath: "#/properties/service/properties/description/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                            return false;
                                          }
                                        }
                                        var valid13 = _errs52 === errors;
                                      } else {
                                        var valid13 = true;
                                      }
                                      if (valid13) {
                                        if (data19.languages !== void 0) {
                                          let data22 = data19.languages;
                                          const _errs54 = errors;
                                          if (errors === _errs54) {
                                            if (Array.isArray(data22)) {
                                              if (data22.length > 16) {
                                                validate24.errors = [{ instancePath: instancePath + "/service/languages", schemaPath: "#/properties/service/properties/languages/maxItems", keyword: "maxItems", params: { limit: 16 }, message: "must NOT have more than 16 items" }];
                                                return false;
                                              } else {
                                                var valid14 = true;
                                                const len0 = data22.length;
                                                for (let i0 = 0; i0 < len0; i0++) {
                                                  let data23 = data22[i0];
                                                  const _errs56 = errors;
                                                  if (errors === _errs56) {
                                                    if (typeof data23 === "string") {
                                                      if (func2(data23) > 35) {
                                                        validate24.errors = [{ instancePath: instancePath + "/service/languages/" + i0, schemaPath: "#/properties/service/properties/languages/items/maxLength", keyword: "maxLength", params: { limit: 35 }, message: "must NOT have more than 35 characters" }];
                                                        return false;
                                                      } else {
                                                        if (func2(data23) < 2) {
                                                          validate24.errors = [{ instancePath: instancePath + "/service/languages/" + i0, schemaPath: "#/properties/service/properties/languages/items/minLength", keyword: "minLength", params: { limit: 2 }, message: "must NOT have fewer than 2 characters" }];
                                                          return false;
                                                        } else {
                                                          if (!pattern35.test(data23)) {
                                                            validate24.errors = [{ instancePath: instancePath + "/service/languages/" + i0, schemaPath: "#/properties/service/properties/languages/items/pattern", keyword: "pattern", params: { pattern: "^[a-z]{2,8}(?:-[a-z0-9]{1,8})*$" }, message: 'must match pattern "^[a-z]{2,8}(?:-[a-z0-9]{1,8})*$"' }];
                                                            return false;
                                                          }
                                                        }
                                                      }
                                                    } else {
                                                      validate24.errors = [{ instancePath: instancePath + "/service/languages/" + i0, schemaPath: "#/properties/service/properties/languages/items/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                                      return false;
                                                    }
                                                  }
                                                  var valid14 = _errs56 === errors;
                                                  if (!valid14) {
                                                    break;
                                                  }
                                                }
                                                if (valid14) {
                                                  let i1 = data22.length;
                                                  let j0;
                                                  if (i1 > 1) {
                                                    const indices0 = {};
                                                    for (; i1--; ) {
                                                      let item0 = data22[i1];
                                                      if (typeof item0 !== "string") {
                                                        continue;
                                                      }
                                                      if (typeof indices0[item0] == "number") {
                                                        j0 = indices0[item0];
                                                        validate24.errors = [{ instancePath: instancePath + "/service/languages", schemaPath: "#/properties/service/properties/languages/uniqueItems", keyword: "uniqueItems", params: { i: i1, j: j0 }, message: "must NOT have duplicate items (items ## " + j0 + " and " + i1 + " are identical)" }];
                                                        return false;
                                                        break;
                                                      }
                                                      indices0[item0] = i1;
                                                    }
                                                  }
                                                }
                                              }
                                            } else {
                                              validate24.errors = [{ instancePath: instancePath + "/service/languages", schemaPath: "#/properties/service/properties/languages/type", keyword: "type", params: { type: "array" }, message: "must be array" }];
                                              return false;
                                            }
                                          }
                                          var valid13 = _errs54 === errors;
                                        } else {
                                          var valid13 = true;
                                        }
                                        if (valid13) {
                                          if (data19.categories !== void 0) {
                                            let data24 = data19.categories;
                                            const _errs58 = errors;
                                            if (errors === _errs58) {
                                              if (Array.isArray(data24)) {
                                                if (data24.length > 32) {
                                                  validate24.errors = [{ instancePath: instancePath + "/service/categories", schemaPath: "#/properties/service/properties/categories/maxItems", keyword: "maxItems", params: { limit: 32 }, message: "must NOT have more than 32 items" }];
                                                  return false;
                                                } else {
                                                  var valid16 = true;
                                                  const len1 = data24.length;
                                                  for (let i2 = 0; i2 < len1; i2++) {
                                                    let data25 = data24[i2];
                                                    const _errs60 = errors;
                                                    if (errors === _errs60) {
                                                      if (typeof data25 === "string") {
                                                        if (func2(data25) > 64) {
                                                          validate24.errors = [{ instancePath: instancePath + "/service/categories/" + i2, schemaPath: "#/properties/service/properties/categories/items/maxLength", keyword: "maxLength", params: { limit: 64 }, message: "must NOT have more than 64 characters" }];
                                                          return false;
                                                        } else {
                                                          if (func2(data25) < 1) {
                                                            validate24.errors = [{ instancePath: instancePath + "/service/categories/" + i2, schemaPath: "#/properties/service/properties/categories/items/minLength", keyword: "minLength", params: { limit: 1 }, message: "must NOT have fewer than 1 characters" }];
                                                            return false;
                                                          } else {
                                                            if (!pattern36.test(data25)) {
                                                              validate24.errors = [{ instancePath: instancePath + "/service/categories/" + i2, schemaPath: "#/properties/service/properties/categories/items/pattern", keyword: "pattern", params: { pattern: "^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$" }, message: 'must match pattern "^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$"' }];
                                                              return false;
                                                            }
                                                          }
                                                        }
                                                      } else {
                                                        validate24.errors = [{ instancePath: instancePath + "/service/categories/" + i2, schemaPath: "#/properties/service/properties/categories/items/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                                        return false;
                                                      }
                                                    }
                                                    var valid16 = _errs60 === errors;
                                                    if (!valid16) {
                                                      break;
                                                    }
                                                  }
                                                  if (valid16) {
                                                    let i3 = data24.length;
                                                    let j1;
                                                    if (i3 > 1) {
                                                      const indices1 = {};
                                                      for (; i3--; ) {
                                                        let item1 = data24[i3];
                                                        if (typeof item1 !== "string") {
                                                          continue;
                                                        }
                                                        if (typeof indices1[item1] == "number") {
                                                          j1 = indices1[item1];
                                                          validate24.errors = [{ instancePath: instancePath + "/service/categories", schemaPath: "#/properties/service/properties/categories/uniqueItems", keyword: "uniqueItems", params: { i: i3, j: j1 }, message: "must NOT have duplicate items (items ## " + j1 + " and " + i3 + " are identical)" }];
                                                          return false;
                                                          break;
                                                        }
                                                        indices1[item1] = i3;
                                                      }
                                                    }
                                                  }
                                                }
                                              } else {
                                                validate24.errors = [{ instancePath: instancePath + "/service/categories", schemaPath: "#/properties/service/properties/categories/type", keyword: "type", params: { type: "array" }, message: "must be array" }];
                                                return false;
                                              }
                                            }
                                            var valid13 = _errs58 === errors;
                                          } else {
                                            var valid13 = true;
                                          }
                                        }
                                      }
                                    }
                                  }
                                }
                              } else {
                                validate24.errors = [{ instancePath: instancePath + "/service", schemaPath: "#/properties/service/type", keyword: "type", params: { type: "object" }, message: "must be object" }];
                                return false;
                              }
                            }
                            var valid10 = _errs47 === errors;
                          } else {
                            var valid10 = true;
                          }
                          if (valid10) {
                            if (data.links !== void 0) {
                              let data26 = data.links;
                              const _errs62 = errors;
                              if (errors === _errs62) {
                                if (data26 && typeof data26 == "object" && !Array.isArray(data26)) {
                                  const _errs64 = errors;
                                  for (const key3 in data26) {
                                    if (!(key3 === "related_onion_services" || key3 === "clearnet")) {
                                      validate24.errors = [{ instancePath: instancePath + "/links", schemaPath: "#/properties/links/additionalProperties", keyword: "additionalProperties", params: { additionalProperty: key3 }, message: "must NOT have additional properties" }];
                                      return false;
                                      break;
                                    }
                                  }
                                  if (_errs64 === errors) {
                                    if (data26.related_onion_services !== void 0) {
                                      let data27 = data26.related_onion_services;
                                      const _errs65 = errors;
                                      if (errors === _errs65) {
                                        if (Array.isArray(data27)) {
                                          if (data27.length > 32) {
                                            validate24.errors = [{ instancePath: instancePath + "/links/related_onion_services", schemaPath: "#/properties/links/properties/related_onion_services/maxItems", keyword: "maxItems", params: { limit: 32 }, message: "must NOT have more than 32 items" }];
                                            return false;
                                          } else {
                                            var valid19 = true;
                                            const len2 = data27.length;
                                            for (let i4 = 0; i4 < len2; i4++) {
                                              let data28 = data27[i4];
                                              const _errs67 = errors;
                                              if (errors === _errs67) {
                                                if (typeof data28 === "string") {
                                                  if (func2(data28) > 62) {
                                                    validate24.errors = [{ instancePath: instancePath + "/links/related_onion_services/" + i4, schemaPath: "#/properties/links/properties/related_onion_services/items/maxLength", keyword: "maxLength", params: { limit: 62 }, message: "must NOT have more than 62 characters" }];
                                                    return false;
                                                  } else {
                                                    if (func2(data28) < 62) {
                                                      validate24.errors = [{ instancePath: instancePath + "/links/related_onion_services/" + i4, schemaPath: "#/properties/links/properties/related_onion_services/items/minLength", keyword: "minLength", params: { limit: 62 }, message: "must NOT have fewer than 62 characters" }];
                                                      return false;
                                                    } else {
                                                      if (!pattern5.test(data28)) {
                                                        validate24.errors = [{ instancePath: instancePath + "/links/related_onion_services/" + i4, schemaPath: "#/properties/links/properties/related_onion_services/items/pattern", keyword: "pattern", params: { pattern: "^[a-z2-7]{56}\\.onion$" }, message: 'must match pattern "^[a-z2-7]{56}\\.onion$"' }];
                                                        return false;
                                                      }
                                                    }
                                                  }
                                                } else {
                                                  validate24.errors = [{ instancePath: instancePath + "/links/related_onion_services/" + i4, schemaPath: "#/properties/links/properties/related_onion_services/items/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                                  return false;
                                                }
                                              }
                                              var valid19 = _errs67 === errors;
                                              if (!valid19) {
                                                break;
                                              }
                                            }
                                            if (valid19) {
                                              let i5 = data27.length;
                                              let j2;
                                              if (i5 > 1) {
                                                const indices2 = {};
                                                for (; i5--; ) {
                                                  let item2 = data27[i5];
                                                  if (typeof item2 !== "string") {
                                                    continue;
                                                  }
                                                  if (typeof indices2[item2] == "number") {
                                                    j2 = indices2[item2];
                                                    validate24.errors = [{ instancePath: instancePath + "/links/related_onion_services", schemaPath: "#/properties/links/properties/related_onion_services/uniqueItems", keyword: "uniqueItems", params: { i: i5, j: j2 }, message: "must NOT have duplicate items (items ## " + j2 + " and " + i5 + " are identical)" }];
                                                    return false;
                                                    break;
                                                  }
                                                  indices2[item2] = i5;
                                                }
                                              }
                                            }
                                          }
                                        } else {
                                          validate24.errors = [{ instancePath: instancePath + "/links/related_onion_services", schemaPath: "#/properties/links/properties/related_onion_services/type", keyword: "type", params: { type: "array" }, message: "must be array" }];
                                          return false;
                                        }
                                      }
                                      var valid18 = _errs65 === errors;
                                    } else {
                                      var valid18 = true;
                                    }
                                    if (valid18) {
                                      if (data26.clearnet !== void 0) {
                                        let data29 = data26.clearnet;
                                        const _errs69 = errors;
                                        if (errors === _errs69) {
                                          if (Array.isArray(data29)) {
                                            if (data29.length > 8) {
                                              validate24.errors = [{ instancePath: instancePath + "/links/clearnet", schemaPath: "#/properties/links/properties/clearnet/maxItems", keyword: "maxItems", params: { limit: 8 }, message: "must NOT have more than 8 items" }];
                                              return false;
                                            } else {
                                              var valid21 = true;
                                              const len3 = data29.length;
                                              for (let i6 = 0; i6 < len3; i6++) {
                                                let data30 = data29[i6];
                                                const _errs71 = errors;
                                                if (errors === _errs71) {
                                                  if (typeof data30 === "string") {
                                                    if (func2(data30) > 2048) {
                                                      validate24.errors = [{ instancePath: instancePath + "/links/clearnet/" + i6, schemaPath: "#/properties/links/properties/clearnet/items/maxLength", keyword: "maxLength", params: { limit: 2048 }, message: "must NOT have more than 2048 characters" }];
                                                      return false;
                                                    } else {
                                                      if (func2(data30) < 8) {
                                                        validate24.errors = [{ instancePath: instancePath + "/links/clearnet/" + i6, schemaPath: "#/properties/links/properties/clearnet/items/minLength", keyword: "minLength", params: { limit: 8 }, message: "must NOT have fewer than 8 characters" }];
                                                        return false;
                                                      } else {
                                                        if (!pattern38.test(data30)) {
                                                          validate24.errors = [{ instancePath: instancePath + "/links/clearnet/" + i6, schemaPath: "#/properties/links/properties/clearnet/items/pattern", keyword: "pattern", params: { pattern: "^https://[^/@\\s]+(?:/[^\\s]*)?$" }, message: 'must match pattern "^https://[^/@\\s]+(?:/[^\\s]*)?$"' }];
                                                          return false;
                                                        }
                                                      }
                                                    }
                                                  } else {
                                                    validate24.errors = [{ instancePath: instancePath + "/links/clearnet/" + i6, schemaPath: "#/properties/links/properties/clearnet/items/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                                    return false;
                                                  }
                                                }
                                                var valid21 = _errs71 === errors;
                                                if (!valid21) {
                                                  break;
                                                }
                                              }
                                              if (valid21) {
                                                let i7 = data29.length;
                                                let j3;
                                                if (i7 > 1) {
                                                  const indices3 = {};
                                                  for (; i7--; ) {
                                                    let item3 = data29[i7];
                                                    if (typeof item3 !== "string") {
                                                      continue;
                                                    }
                                                    if (typeof indices3[item3] == "number") {
                                                      j3 = indices3[item3];
                                                      validate24.errors = [{ instancePath: instancePath + "/links/clearnet", schemaPath: "#/properties/links/properties/clearnet/uniqueItems", keyword: "uniqueItems", params: { i: i7, j: j3 }, message: "must NOT have duplicate items (items ## " + j3 + " and " + i7 + " are identical)" }];
                                                      return false;
                                                      break;
                                                    }
                                                    indices3[item3] = i7;
                                                  }
                                                }
                                              }
                                            }
                                          } else {
                                            validate24.errors = [{ instancePath: instancePath + "/links/clearnet", schemaPath: "#/properties/links/properties/clearnet/type", keyword: "type", params: { type: "array" }, message: "must be array" }];
                                            return false;
                                          }
                                        }
                                        var valid18 = _errs69 === errors;
                                      } else {
                                        var valid18 = true;
                                      }
                                    }
                                  }
                                } else {
                                  validate24.errors = [{ instancePath: instancePath + "/links", schemaPath: "#/properties/links/type", keyword: "type", params: { type: "object" }, message: "must be object" }];
                                  return false;
                                }
                              }
                              var valid10 = _errs62 === errors;
                            } else {
                              var valid10 = true;
                            }
                            if (valid10) {
                              if (data.published_at !== void 0) {
                                let data31 = data.published_at;
                                const _errs73 = errors;
                                if (errors === _errs73) {
                                  if (typeof data31 === "string") {
                                    if (func2(data31) > 20) {
                                      validate24.errors = [{ instancePath: instancePath + "/published_at", schemaPath: "#/properties/published_at/maxLength", keyword: "maxLength", params: { limit: 20 }, message: "must NOT have more than 20 characters" }];
                                      return false;
                                    } else {
                                      if (func2(data31) < 20) {
                                        validate24.errors = [{ instancePath: instancePath + "/published_at", schemaPath: "#/properties/published_at/minLength", keyword: "minLength", params: { limit: 20 }, message: "must NOT have fewer than 20 characters" }];
                                        return false;
                                      } else {
                                        if (!pattern8.test(data31)) {
                                          validate24.errors = [{ instancePath: instancePath + "/published_at", schemaPath: "#/properties/published_at/pattern", keyword: "pattern", params: { pattern: "^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$" }, message: 'must match pattern "^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$"' }];
                                          return false;
                                        }
                                      }
                                    }
                                  } else {
                                    validate24.errors = [{ instancePath: instancePath + "/published_at", schemaPath: "#/properties/published_at/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                    return false;
                                  }
                                }
                                var valid10 = _errs73 === errors;
                              } else {
                                var valid10 = true;
                              }
                              if (valid10) {
                                if (data.signature !== void 0) {
                                  let data32 = data.signature;
                                  const _errs75 = errors;
                                  if (errors === _errs75) {
                                    if (typeof data32 === "string") {
                                      if (func2(data32) > 97) {
                                        validate24.errors = [{ instancePath: instancePath + "/signature", schemaPath: "#/properties/signature/maxLength", keyword: "maxLength", params: { limit: 97 }, message: "must NOT have more than 97 characters" }];
                                        return false;
                                      } else {
                                        if (func2(data32) < 97) {
                                          validate24.errors = [{ instancePath: instancePath + "/signature", schemaPath: "#/properties/signature/minLength", keyword: "minLength", params: { limit: 97 }, message: "must NOT have fewer than 97 characters" }];
                                          return false;
                                        } else {
                                          if (!pattern9.test(data32)) {
                                            validate24.errors = [{ instancePath: instancePath + "/signature", schemaPath: "#/properties/signature/pattern", keyword: "pattern", params: { pattern: "^ed25519sig:[A-Za-z0-9_-]{86}$" }, message: 'must match pattern "^ed25519sig:[A-Za-z0-9_-]{86}$"' }];
                                            return false;
                                          }
                                        }
                                      }
                                    } else {
                                      validate24.errors = [{ instancePath: instancePath + "/signature", schemaPath: "#/properties/signature/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                      return false;
                                    }
                                  }
                                  var valid10 = _errs75 === errors;
                                } else {
                                  var valid10 = true;
                                }
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    } else {
      validate24.errors = [{ instancePath, schemaPath: "#/type", keyword: "type", params: { type: "object" }, message: "must be object" }];
      return false;
    }
  }
  validate24.errors = vErrors;
  return errors === 0;
}
validate24.evaluated = { "props": true, "dynamicProps": false, "dynamicItems": false };
var snapshot_descriptor = validate25;
var schema36 = { "type": "object", "properties": { "protocol": { "const": "Onion Service Discovery" }, "protocol_version": { "const": 1 }, "record_type": { "const": "snapshot_descriptor" }, "node_identity_public_key": { "type": "string", "minLength": 51, "maxLength": 51, "pattern": "^ed25519:[A-Za-z0-9_-]{43}$" }, "generation": { "type": "integer", "minimum": 1, "maximum": 9007199254740991 }, "previous_checkpoint_hash": { "anyOf": [{ "type": "string", "minLength": 71, "maxLength": 71, "pattern": "^sha256:[0-9a-f]{64}$" }, { "type": "null" }] }, "created_at": { "type": "string", "minLength": 20, "maxLength": 20, "pattern": "^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$" }, "root_hash": { "type": "string", "minLength": 71, "maxLength": 71, "pattern": "^sha256:[0-9a-f]{64}$" }, "record_count": { "type": "integer", "minimum": 0, "maximum": 9007199254740991 }, "cursor": { "type": "string", "minLength": 1, "maxLength": 128, "pattern": "^[A-Za-z0-9_-]+$" }, "chunks": { "type": "array", "items": { "type": "object", "properties": { "hash": { "type": "string", "minLength": 71, "maxLength": 71, "pattern": "^sha256:[0-9a-f]{64}$" }, "bytes": { "type": "integer", "minimum": 2, "maximum": 524288 }, "record_count": { "type": "integer", "minimum": 0, "maximum": 256 } }, "required": ["hash", "bytes", "record_count"], "additionalProperties": false }, "maxItems": 256 }, "signature": { "type": "string", "minLength": 97, "maxLength": 97, "pattern": "^ed25519sig:[A-Za-z0-9_-]{86}$" } }, "required": ["protocol", "protocol_version", "record_type", "node_identity_public_key", "generation", "previous_checkpoint_hash", "created_at", "root_hash", "record_count", "cursor", "chunks", "signature"], "additionalProperties": false, "$schema": "https://json-schema.org/draft/2020-12/schema", "$id": "https://github.com/ShawnJeromeMcCray/Onion-Service-Discovery/schemas/draft-v1/snapshot_descriptor.json", "title": "Onion Service Discovery snapshot_descriptor", "$comment": "Implementation draft; protocol freeze and human crypto approval are outstanding.", "allOf": [{ "if": { "properties": { "generation": { "const": 1 } } }, "then": { "properties": { "previous_checkpoint_hash": { "type": "null" } } }, "else": { "properties": { "previous_checkpoint_hash": { "type": "string", "minLength": 71, "maxLength": 71, "pattern": "^sha256:[0-9a-f]{64}$" } } } }] };
var pattern46 = new RegExp("^[A-Za-z0-9_-]+$", "u");
function validate25(data, { instancePath = "", parentData, parentDataProperty, rootData = data, dynamicAnchors = {} } = {}) {
  ;
  let vErrors = null;
  let errors = 0;
  const evaluated0 = validate25.evaluated;
  if (evaluated0.dynamicProps) {
    evaluated0.props = void 0;
  }
  if (evaluated0.dynamicItems) {
    evaluated0.items = void 0;
  }
  const _errs3 = errors;
  let valid1 = true;
  const _errs4 = errors;
  if (data && typeof data == "object" && !Array.isArray(data)) {
    if (data.generation !== void 0) {
      if (1 !== data.generation) {
        const err0 = {};
        if (vErrors === null) {
          vErrors = [err0];
        } else {
          vErrors.push(err0);
        }
        errors++;
      }
    }
  }
  var _valid0 = _errs4 === errors;
  errors = _errs3;
  if (vErrors !== null) {
    if (_errs3) {
      vErrors.length = _errs3;
    } else {
      vErrors = null;
    }
  }
  let ifClause0;
  if (_valid0) {
    const _errs6 = errors;
    if (data && typeof data == "object" && !Array.isArray(data)) {
      if (data.previous_checkpoint_hash !== void 0) {
        if (data.previous_checkpoint_hash !== null) {
          validate25.errors = [{ instancePath: instancePath + "/previous_checkpoint_hash", schemaPath: "#/allOf/0/then/properties/previous_checkpoint_hash/type", keyword: "type", params: { type: "null" }, message: "must be null" }];
          return false;
        }
      }
    }
    var _valid0 = _errs6 === errors;
    valid1 = _valid0;
    if (valid1) {
      var props0 = {};
      props0.previous_checkpoint_hash = true;
      props0.generation = true;
    }
    ifClause0 = "then";
  } else {
    const _errs9 = errors;
    if (data && typeof data == "object" && !Array.isArray(data)) {
      if (data.previous_checkpoint_hash !== void 0) {
        let data2 = data.previous_checkpoint_hash;
        const _errs10 = errors;
        if (errors === _errs10) {
          if (typeof data2 === "string") {
            if (func2(data2) > 71) {
              validate25.errors = [{ instancePath: instancePath + "/previous_checkpoint_hash", schemaPath: "#/allOf/0/else/properties/previous_checkpoint_hash/maxLength", keyword: "maxLength", params: { limit: 71 }, message: "must NOT have more than 71 characters" }];
              return false;
            } else {
              if (func2(data2) < 71) {
                validate25.errors = [{ instancePath: instancePath + "/previous_checkpoint_hash", schemaPath: "#/allOf/0/else/properties/previous_checkpoint_hash/minLength", keyword: "minLength", params: { limit: 71 }, message: "must NOT have fewer than 71 characters" }];
                return false;
              } else {
                if (!pattern7.test(data2)) {
                  validate25.errors = [{ instancePath: instancePath + "/previous_checkpoint_hash", schemaPath: "#/allOf/0/else/properties/previous_checkpoint_hash/pattern", keyword: "pattern", params: { pattern: "^sha256:[0-9a-f]{64}$" }, message: 'must match pattern "^sha256:[0-9a-f]{64}$"' }];
                  return false;
                }
              }
            }
          } else {
            validate25.errors = [{ instancePath: instancePath + "/previous_checkpoint_hash", schemaPath: "#/allOf/0/else/properties/previous_checkpoint_hash/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
            return false;
          }
        }
      }
    }
    var _valid0 = _errs9 === errors;
    valid1 = _valid0;
    if (valid1) {
      if (props0 !== true) {
        props0 = props0 || {};
        props0.previous_checkpoint_hash = true;
      }
    }
    ifClause0 = "else";
  }
  if (!valid1) {
    const err1 = { instancePath, schemaPath: "#/allOf/0/if", keyword: "if", params: { failingKeyword: ifClause0 }, message: 'must match "' + ifClause0 + '" schema' };
    if (vErrors === null) {
      vErrors = [err1];
    } else {
      vErrors.push(err1);
    }
    errors++;
    validate25.errors = vErrors;
    return false;
  }
  if (errors === 0) {
    if (data && typeof data == "object" && !Array.isArray(data)) {
      let missing0;
      if (data.protocol === void 0 && (missing0 = "protocol") || data.protocol_version === void 0 && (missing0 = "protocol_version") || data.record_type === void 0 && (missing0 = "record_type") || data.node_identity_public_key === void 0 && (missing0 = "node_identity_public_key") || data.generation === void 0 && (missing0 = "generation") || data.previous_checkpoint_hash === void 0 && (missing0 = "previous_checkpoint_hash") || data.created_at === void 0 && (missing0 = "created_at") || data.root_hash === void 0 && (missing0 = "root_hash") || data.record_count === void 0 && (missing0 = "record_count") || data.cursor === void 0 && (missing0 = "cursor") || data.chunks === void 0 && (missing0 = "chunks") || data.signature === void 0 && (missing0 = "signature")) {
        validate25.errors = [{ instancePath, schemaPath: "#/required", keyword: "required", params: { missingProperty: missing0 }, message: "must have required property '" + missing0 + "'" }];
        return false;
      } else {
        const _errs12 = errors;
        for (const key0 in data) {
          if (!func1.call(schema36.properties, key0)) {
            validate25.errors = [{ instancePath, schemaPath: "#/additionalProperties", keyword: "additionalProperties", params: { additionalProperty: key0 }, message: "must NOT have additional properties" }];
            return false;
            break;
          }
        }
        if (_errs12 === errors) {
          if (data.protocol !== void 0) {
            const _errs13 = errors;
            if ("Onion Service Discovery" !== data.protocol) {
              validate25.errors = [{ instancePath: instancePath + "/protocol", schemaPath: "#/properties/protocol/const", keyword: "const", params: { allowedValue: "Onion Service Discovery" }, message: "must be equal to constant" }];
              return false;
            }
            var valid5 = _errs13 === errors;
          } else {
            var valid5 = true;
          }
          if (valid5) {
            if (data.protocol_version !== void 0) {
              const _errs14 = errors;
              if (1 !== data.protocol_version) {
                validate25.errors = [{ instancePath: instancePath + "/protocol_version", schemaPath: "#/properties/protocol_version/const", keyword: "const", params: { allowedValue: 1 }, message: "must be equal to constant" }];
                return false;
              }
              var valid5 = _errs14 === errors;
            } else {
              var valid5 = true;
            }
            if (valid5) {
              if (data.record_type !== void 0) {
                const _errs15 = errors;
                if ("snapshot_descriptor" !== data.record_type) {
                  validate25.errors = [{ instancePath: instancePath + "/record_type", schemaPath: "#/properties/record_type/const", keyword: "const", params: { allowedValue: "snapshot_descriptor" }, message: "must be equal to constant" }];
                  return false;
                }
                var valid5 = _errs15 === errors;
              } else {
                var valid5 = true;
              }
              if (valid5) {
                if (data.node_identity_public_key !== void 0) {
                  let data6 = data.node_identity_public_key;
                  const _errs16 = errors;
                  if (errors === _errs16) {
                    if (typeof data6 === "string") {
                      if (func2(data6) > 51) {
                        validate25.errors = [{ instancePath: instancePath + "/node_identity_public_key", schemaPath: "#/properties/node_identity_public_key/maxLength", keyword: "maxLength", params: { limit: 51 }, message: "must NOT have more than 51 characters" }];
                        return false;
                      } else {
                        if (func2(data6) < 51) {
                          validate25.errors = [{ instancePath: instancePath + "/node_identity_public_key", schemaPath: "#/properties/node_identity_public_key/minLength", keyword: "minLength", params: { limit: 51 }, message: "must NOT have fewer than 51 characters" }];
                          return false;
                        } else {
                          if (!pattern4.test(data6)) {
                            validate25.errors = [{ instancePath: instancePath + "/node_identity_public_key", schemaPath: "#/properties/node_identity_public_key/pattern", keyword: "pattern", params: { pattern: "^ed25519:[A-Za-z0-9_-]{43}$" }, message: 'must match pattern "^ed25519:[A-Za-z0-9_-]{43}$"' }];
                            return false;
                          }
                        }
                      }
                    } else {
                      validate25.errors = [{ instancePath: instancePath + "/node_identity_public_key", schemaPath: "#/properties/node_identity_public_key/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                      return false;
                    }
                  }
                  var valid5 = _errs16 === errors;
                } else {
                  var valid5 = true;
                }
                if (valid5) {
                  if (data.generation !== void 0) {
                    let data7 = data.generation;
                    const _errs18 = errors;
                    if (!(typeof data7 == "number" && (!(data7 % 1) && !isNaN(data7)) && isFinite(data7))) {
                      validate25.errors = [{ instancePath: instancePath + "/generation", schemaPath: "#/properties/generation/type", keyword: "type", params: { type: "integer" }, message: "must be integer" }];
                      return false;
                    }
                    if (errors === _errs18) {
                      if (typeof data7 == "number" && isFinite(data7)) {
                        if (data7 > 9007199254740991 || isNaN(data7)) {
                          validate25.errors = [{ instancePath: instancePath + "/generation", schemaPath: "#/properties/generation/maximum", keyword: "maximum", params: { comparison: "<=", limit: 9007199254740991 }, message: "must be <= 9007199254740991" }];
                          return false;
                        } else {
                          if (data7 < 1 || isNaN(data7)) {
                            validate25.errors = [{ instancePath: instancePath + "/generation", schemaPath: "#/properties/generation/minimum", keyword: "minimum", params: { comparison: ">=", limit: 1 }, message: "must be >= 1" }];
                            return false;
                          }
                        }
                      }
                    }
                    var valid5 = _errs18 === errors;
                  } else {
                    var valid5 = true;
                  }
                  if (valid5) {
                    if (data.previous_checkpoint_hash !== void 0) {
                      let data8 = data.previous_checkpoint_hash;
                      const _errs20 = errors;
                      const _errs21 = errors;
                      let valid6 = false;
                      const _errs22 = errors;
                      if (errors === _errs22) {
                        if (typeof data8 === "string") {
                          if (func2(data8) > 71) {
                            const err2 = { instancePath: instancePath + "/previous_checkpoint_hash", schemaPath: "#/properties/previous_checkpoint_hash/anyOf/0/maxLength", keyword: "maxLength", params: { limit: 71 }, message: "must NOT have more than 71 characters" };
                            if (vErrors === null) {
                              vErrors = [err2];
                            } else {
                              vErrors.push(err2);
                            }
                            errors++;
                          } else {
                            if (func2(data8) < 71) {
                              const err3 = { instancePath: instancePath + "/previous_checkpoint_hash", schemaPath: "#/properties/previous_checkpoint_hash/anyOf/0/minLength", keyword: "minLength", params: { limit: 71 }, message: "must NOT have fewer than 71 characters" };
                              if (vErrors === null) {
                                vErrors = [err3];
                              } else {
                                vErrors.push(err3);
                              }
                              errors++;
                            } else {
                              if (!pattern7.test(data8)) {
                                const err4 = { instancePath: instancePath + "/previous_checkpoint_hash", schemaPath: "#/properties/previous_checkpoint_hash/anyOf/0/pattern", keyword: "pattern", params: { pattern: "^sha256:[0-9a-f]{64}$" }, message: 'must match pattern "^sha256:[0-9a-f]{64}$"' };
                                if (vErrors === null) {
                                  vErrors = [err4];
                                } else {
                                  vErrors.push(err4);
                                }
                                errors++;
                              }
                            }
                          }
                        } else {
                          const err5 = { instancePath: instancePath + "/previous_checkpoint_hash", schemaPath: "#/properties/previous_checkpoint_hash/anyOf/0/type", keyword: "type", params: { type: "string" }, message: "must be string" };
                          if (vErrors === null) {
                            vErrors = [err5];
                          } else {
                            vErrors.push(err5);
                          }
                          errors++;
                        }
                      }
                      var _valid1 = _errs22 === errors;
                      valid6 = valid6 || _valid1;
                      const _errs24 = errors;
                      if (data8 !== null) {
                        const err6 = { instancePath: instancePath + "/previous_checkpoint_hash", schemaPath: "#/properties/previous_checkpoint_hash/anyOf/1/type", keyword: "type", params: { type: "null" }, message: "must be null" };
                        if (vErrors === null) {
                          vErrors = [err6];
                        } else {
                          vErrors.push(err6);
                        }
                        errors++;
                      }
                      var _valid1 = _errs24 === errors;
                      valid6 = valid6 || _valid1;
                      if (!valid6) {
                        const err7 = { instancePath: instancePath + "/previous_checkpoint_hash", schemaPath: "#/properties/previous_checkpoint_hash/anyOf", keyword: "anyOf", params: {}, message: "must match a schema in anyOf" };
                        if (vErrors === null) {
                          vErrors = [err7];
                        } else {
                          vErrors.push(err7);
                        }
                        errors++;
                        validate25.errors = vErrors;
                        return false;
                      } else {
                        errors = _errs21;
                        if (vErrors !== null) {
                          if (_errs21) {
                            vErrors.length = _errs21;
                          } else {
                            vErrors = null;
                          }
                        }
                      }
                      var valid5 = _errs20 === errors;
                    } else {
                      var valid5 = true;
                    }
                    if (valid5) {
                      if (data.created_at !== void 0) {
                        let data9 = data.created_at;
                        const _errs26 = errors;
                        if (errors === _errs26) {
                          if (typeof data9 === "string") {
                            if (func2(data9) > 20) {
                              validate25.errors = [{ instancePath: instancePath + "/created_at", schemaPath: "#/properties/created_at/maxLength", keyword: "maxLength", params: { limit: 20 }, message: "must NOT have more than 20 characters" }];
                              return false;
                            } else {
                              if (func2(data9) < 20) {
                                validate25.errors = [{ instancePath: instancePath + "/created_at", schemaPath: "#/properties/created_at/minLength", keyword: "minLength", params: { limit: 20 }, message: "must NOT have fewer than 20 characters" }];
                                return false;
                              } else {
                                if (!pattern8.test(data9)) {
                                  validate25.errors = [{ instancePath: instancePath + "/created_at", schemaPath: "#/properties/created_at/pattern", keyword: "pattern", params: { pattern: "^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$" }, message: 'must match pattern "^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$"' }];
                                  return false;
                                }
                              }
                            }
                          } else {
                            validate25.errors = [{ instancePath: instancePath + "/created_at", schemaPath: "#/properties/created_at/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                            return false;
                          }
                        }
                        var valid5 = _errs26 === errors;
                      } else {
                        var valid5 = true;
                      }
                      if (valid5) {
                        if (data.root_hash !== void 0) {
                          let data10 = data.root_hash;
                          const _errs28 = errors;
                          if (errors === _errs28) {
                            if (typeof data10 === "string") {
                              if (func2(data10) > 71) {
                                validate25.errors = [{ instancePath: instancePath + "/root_hash", schemaPath: "#/properties/root_hash/maxLength", keyword: "maxLength", params: { limit: 71 }, message: "must NOT have more than 71 characters" }];
                                return false;
                              } else {
                                if (func2(data10) < 71) {
                                  validate25.errors = [{ instancePath: instancePath + "/root_hash", schemaPath: "#/properties/root_hash/minLength", keyword: "minLength", params: { limit: 71 }, message: "must NOT have fewer than 71 characters" }];
                                  return false;
                                } else {
                                  if (!pattern7.test(data10)) {
                                    validate25.errors = [{ instancePath: instancePath + "/root_hash", schemaPath: "#/properties/root_hash/pattern", keyword: "pattern", params: { pattern: "^sha256:[0-9a-f]{64}$" }, message: 'must match pattern "^sha256:[0-9a-f]{64}$"' }];
                                    return false;
                                  }
                                }
                              }
                            } else {
                              validate25.errors = [{ instancePath: instancePath + "/root_hash", schemaPath: "#/properties/root_hash/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                              return false;
                            }
                          }
                          var valid5 = _errs28 === errors;
                        } else {
                          var valid5 = true;
                        }
                        if (valid5) {
                          if (data.record_count !== void 0) {
                            let data11 = data.record_count;
                            const _errs30 = errors;
                            if (!(typeof data11 == "number" && (!(data11 % 1) && !isNaN(data11)) && isFinite(data11))) {
                              validate25.errors = [{ instancePath: instancePath + "/record_count", schemaPath: "#/properties/record_count/type", keyword: "type", params: { type: "integer" }, message: "must be integer" }];
                              return false;
                            }
                            if (errors === _errs30) {
                              if (typeof data11 == "number" && isFinite(data11)) {
                                if (data11 > 9007199254740991 || isNaN(data11)) {
                                  validate25.errors = [{ instancePath: instancePath + "/record_count", schemaPath: "#/properties/record_count/maximum", keyword: "maximum", params: { comparison: "<=", limit: 9007199254740991 }, message: "must be <= 9007199254740991" }];
                                  return false;
                                } else {
                                  if (data11 < 0 || isNaN(data11)) {
                                    validate25.errors = [{ instancePath: instancePath + "/record_count", schemaPath: "#/properties/record_count/minimum", keyword: "minimum", params: { comparison: ">=", limit: 0 }, message: "must be >= 0" }];
                                    return false;
                                  }
                                }
                              }
                            }
                            var valid5 = _errs30 === errors;
                          } else {
                            var valid5 = true;
                          }
                          if (valid5) {
                            if (data.cursor !== void 0) {
                              let data12 = data.cursor;
                              const _errs32 = errors;
                              if (errors === _errs32) {
                                if (typeof data12 === "string") {
                                  if (func2(data12) > 128) {
                                    validate25.errors = [{ instancePath: instancePath + "/cursor", schemaPath: "#/properties/cursor/maxLength", keyword: "maxLength", params: { limit: 128 }, message: "must NOT have more than 128 characters" }];
                                    return false;
                                  } else {
                                    if (func2(data12) < 1) {
                                      validate25.errors = [{ instancePath: instancePath + "/cursor", schemaPath: "#/properties/cursor/minLength", keyword: "minLength", params: { limit: 1 }, message: "must NOT have fewer than 1 characters" }];
                                      return false;
                                    } else {
                                      if (!pattern46.test(data12)) {
                                        validate25.errors = [{ instancePath: instancePath + "/cursor", schemaPath: "#/properties/cursor/pattern", keyword: "pattern", params: { pattern: "^[A-Za-z0-9_-]+$" }, message: 'must match pattern "^[A-Za-z0-9_-]+$"' }];
                                        return false;
                                      }
                                    }
                                  }
                                } else {
                                  validate25.errors = [{ instancePath: instancePath + "/cursor", schemaPath: "#/properties/cursor/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                  return false;
                                }
                              }
                              var valid5 = _errs32 === errors;
                            } else {
                              var valid5 = true;
                            }
                            if (valid5) {
                              if (data.chunks !== void 0) {
                                let data13 = data.chunks;
                                const _errs34 = errors;
                                if (errors === _errs34) {
                                  if (Array.isArray(data13)) {
                                    if (data13.length > 256) {
                                      validate25.errors = [{ instancePath: instancePath + "/chunks", schemaPath: "#/properties/chunks/maxItems", keyword: "maxItems", params: { limit: 256 }, message: "must NOT have more than 256 items" }];
                                      return false;
                                    } else {
                                      var valid7 = true;
                                      const len0 = data13.length;
                                      for (let i0 = 0; i0 < len0; i0++) {
                                        let data14 = data13[i0];
                                        const _errs36 = errors;
                                        if (errors === _errs36) {
                                          if (data14 && typeof data14 == "object" && !Array.isArray(data14)) {
                                            let missing1;
                                            if (data14.hash === void 0 && (missing1 = "hash") || data14.bytes === void 0 && (missing1 = "bytes") || data14.record_count === void 0 && (missing1 = "record_count")) {
                                              validate25.errors = [{ instancePath: instancePath + "/chunks/" + i0, schemaPath: "#/properties/chunks/items/required", keyword: "required", params: { missingProperty: missing1 }, message: "must have required property '" + missing1 + "'" }];
                                              return false;
                                            } else {
                                              const _errs38 = errors;
                                              for (const key1 in data14) {
                                                if (!(key1 === "hash" || key1 === "bytes" || key1 === "record_count")) {
                                                  validate25.errors = [{ instancePath: instancePath + "/chunks/" + i0, schemaPath: "#/properties/chunks/items/additionalProperties", keyword: "additionalProperties", params: { additionalProperty: key1 }, message: "must NOT have additional properties" }];
                                                  return false;
                                                  break;
                                                }
                                              }
                                              if (_errs38 === errors) {
                                                if (data14.hash !== void 0) {
                                                  let data15 = data14.hash;
                                                  const _errs39 = errors;
                                                  if (errors === _errs39) {
                                                    if (typeof data15 === "string") {
                                                      if (func2(data15) > 71) {
                                                        validate25.errors = [{ instancePath: instancePath + "/chunks/" + i0 + "/hash", schemaPath: "#/properties/chunks/items/properties/hash/maxLength", keyword: "maxLength", params: { limit: 71 }, message: "must NOT have more than 71 characters" }];
                                                        return false;
                                                      } else {
                                                        if (func2(data15) < 71) {
                                                          validate25.errors = [{ instancePath: instancePath + "/chunks/" + i0 + "/hash", schemaPath: "#/properties/chunks/items/properties/hash/minLength", keyword: "minLength", params: { limit: 71 }, message: "must NOT have fewer than 71 characters" }];
                                                          return false;
                                                        } else {
                                                          if (!pattern7.test(data15)) {
                                                            validate25.errors = [{ instancePath: instancePath + "/chunks/" + i0 + "/hash", schemaPath: "#/properties/chunks/items/properties/hash/pattern", keyword: "pattern", params: { pattern: "^sha256:[0-9a-f]{64}$" }, message: 'must match pattern "^sha256:[0-9a-f]{64}$"' }];
                                                            return false;
                                                          }
                                                        }
                                                      }
                                                    } else {
                                                      validate25.errors = [{ instancePath: instancePath + "/chunks/" + i0 + "/hash", schemaPath: "#/properties/chunks/items/properties/hash/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                                      return false;
                                                    }
                                                  }
                                                  var valid8 = _errs39 === errors;
                                                } else {
                                                  var valid8 = true;
                                                }
                                                if (valid8) {
                                                  if (data14.bytes !== void 0) {
                                                    let data16 = data14.bytes;
                                                    const _errs41 = errors;
                                                    if (!(typeof data16 == "number" && (!(data16 % 1) && !isNaN(data16)) && isFinite(data16))) {
                                                      validate25.errors = [{ instancePath: instancePath + "/chunks/" + i0 + "/bytes", schemaPath: "#/properties/chunks/items/properties/bytes/type", keyword: "type", params: { type: "integer" }, message: "must be integer" }];
                                                      return false;
                                                    }
                                                    if (errors === _errs41) {
                                                      if (typeof data16 == "number" && isFinite(data16)) {
                                                        if (data16 > 524288 || isNaN(data16)) {
                                                          validate25.errors = [{ instancePath: instancePath + "/chunks/" + i0 + "/bytes", schemaPath: "#/properties/chunks/items/properties/bytes/maximum", keyword: "maximum", params: { comparison: "<=", limit: 524288 }, message: "must be <= 524288" }];
                                                          return false;
                                                        } else {
                                                          if (data16 < 2 || isNaN(data16)) {
                                                            validate25.errors = [{ instancePath: instancePath + "/chunks/" + i0 + "/bytes", schemaPath: "#/properties/chunks/items/properties/bytes/minimum", keyword: "minimum", params: { comparison: ">=", limit: 2 }, message: "must be >= 2" }];
                                                            return false;
                                                          }
                                                        }
                                                      }
                                                    }
                                                    var valid8 = _errs41 === errors;
                                                  } else {
                                                    var valid8 = true;
                                                  }
                                                  if (valid8) {
                                                    if (data14.record_count !== void 0) {
                                                      let data17 = data14.record_count;
                                                      const _errs43 = errors;
                                                      if (!(typeof data17 == "number" && (!(data17 % 1) && !isNaN(data17)) && isFinite(data17))) {
                                                        validate25.errors = [{ instancePath: instancePath + "/chunks/" + i0 + "/record_count", schemaPath: "#/properties/chunks/items/properties/record_count/type", keyword: "type", params: { type: "integer" }, message: "must be integer" }];
                                                        return false;
                                                      }
                                                      if (errors === _errs43) {
                                                        if (typeof data17 == "number" && isFinite(data17)) {
                                                          if (data17 > 256 || isNaN(data17)) {
                                                            validate25.errors = [{ instancePath: instancePath + "/chunks/" + i0 + "/record_count", schemaPath: "#/properties/chunks/items/properties/record_count/maximum", keyword: "maximum", params: { comparison: "<=", limit: 256 }, message: "must be <= 256" }];
                                                            return false;
                                                          } else {
                                                            if (data17 < 0 || isNaN(data17)) {
                                                              validate25.errors = [{ instancePath: instancePath + "/chunks/" + i0 + "/record_count", schemaPath: "#/properties/chunks/items/properties/record_count/minimum", keyword: "minimum", params: { comparison: ">=", limit: 0 }, message: "must be >= 0" }];
                                                              return false;
                                                            }
                                                          }
                                                        }
                                                      }
                                                      var valid8 = _errs43 === errors;
                                                    } else {
                                                      var valid8 = true;
                                                    }
                                                  }
                                                }
                                              }
                                            }
                                          } else {
                                            validate25.errors = [{ instancePath: instancePath + "/chunks/" + i0, schemaPath: "#/properties/chunks/items/type", keyword: "type", params: { type: "object" }, message: "must be object" }];
                                            return false;
                                          }
                                        }
                                        var valid7 = _errs36 === errors;
                                        if (!valid7) {
                                          break;
                                        }
                                      }
                                    }
                                  } else {
                                    validate25.errors = [{ instancePath: instancePath + "/chunks", schemaPath: "#/properties/chunks/type", keyword: "type", params: { type: "array" }, message: "must be array" }];
                                    return false;
                                  }
                                }
                                var valid5 = _errs34 === errors;
                              } else {
                                var valid5 = true;
                              }
                              if (valid5) {
                                if (data.signature !== void 0) {
                                  let data18 = data.signature;
                                  const _errs45 = errors;
                                  if (errors === _errs45) {
                                    if (typeof data18 === "string") {
                                      if (func2(data18) > 97) {
                                        validate25.errors = [{ instancePath: instancePath + "/signature", schemaPath: "#/properties/signature/maxLength", keyword: "maxLength", params: { limit: 97 }, message: "must NOT have more than 97 characters" }];
                                        return false;
                                      } else {
                                        if (func2(data18) < 97) {
                                          validate25.errors = [{ instancePath: instancePath + "/signature", schemaPath: "#/properties/signature/minLength", keyword: "minLength", params: { limit: 97 }, message: "must NOT have fewer than 97 characters" }];
                                          return false;
                                        } else {
                                          if (!pattern9.test(data18)) {
                                            validate25.errors = [{ instancePath: instancePath + "/signature", schemaPath: "#/properties/signature/pattern", keyword: "pattern", params: { pattern: "^ed25519sig:[A-Za-z0-9_-]{86}$" }, message: 'must match pattern "^ed25519sig:[A-Za-z0-9_-]{86}$"' }];
                                            return false;
                                          }
                                        }
                                      }
                                    } else {
                                      validate25.errors = [{ instancePath: instancePath + "/signature", schemaPath: "#/properties/signature/type", keyword: "type", params: { type: "string" }, message: "must be string" }];
                                      return false;
                                    }
                                  }
                                  var valid5 = _errs45 === errors;
                                } else {
                                  var valid5 = true;
                                }
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    } else {
      validate25.errors = [{ instancePath, schemaPath: "#/type", keyword: "type", params: { type: "object" }, message: "must be object" }];
      return false;
    }
  }
  validate25.errors = vErrors;
  return errors === 0;
}
validate25.evaluated = { "props": true, "dynamicProps": false, "dynamicItems": false };
export {
  direct_observation,
  node_descriptor,
  onion_service_migration,
  service_identity_key_rotation,
  service_manifest,
  snapshot_descriptor
};
