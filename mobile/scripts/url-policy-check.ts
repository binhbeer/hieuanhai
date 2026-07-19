import assert from 'node:assert/strict';
import {
  classifyUrl,
  mapDeepLink,
  requireHttpsOrigin,
  resolveBridgeMediaUrl,
} from '../urlPolicy.ts';

const origin = requireHttpsOrigin('https://genanh.com');

assert.equal(origin, 'https://genanh.com');
assert.throws(() => requireHttpsOrigin('http://genanh.com'));
assert.throws(() => requireHttpsOrigin('https://genanh.com/path'));
assert.equal(classifyUrl('https://genanh.com/creator', origin), 'internal');
assert.equal(classifyUrl('https://genanh.com.evil.example/creator', origin), 'external');
assert.equal(classifyUrl('https://example.com/', origin), 'external');
assert.equal(classifyUrl('mailto:hello@genanh.com', origin), 'external');
assert.equal(classifyUrl('http://genanh.com/creator', origin), 'blocked');
assert.equal(classifyUrl('file:///etc/passwd', origin), 'blocked');
assert.equal(classifyUrl('javascript:alert(1)', origin), 'blocked');
assert.equal(classifyUrl('data:text/html,test', origin), 'blocked');
assert.equal(mapDeepLink('genanh://creator?draft=1', origin), 'https://genanh.com/creator?draft=1');
assert.equal(
  mapDeepLink('https://genanh.com/email/verify/1?expires=2&signature=abc', origin),
  'https://genanh.com/email/verify/1?expires=2&signature=abc',
);
assert.equal(mapDeepLink('https://example.com/creator', origin), null);
const signedDownload = `https://genanh.com/anh/demo/download?expires=2&signature=${'a'.repeat(64)}`;
assert.equal(resolveBridgeMediaUrl(signedDownload, origin), signedDownload);
assert.equal(resolveBridgeMediaUrl('https://genanh.com/anh/demo/download', origin), null);
assert.equal(resolveBridgeMediaUrl('https://example.com/image.jpg', origin), null);

console.log('URL policy self-check passed.');
