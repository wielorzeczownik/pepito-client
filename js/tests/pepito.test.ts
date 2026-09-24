import { existsSync, readFileSync } from 'node:fs';

import { describe, expect, it } from 'vitest';

import { Pepito, REST_URL, SSE_URL } from '../src/pepito.js';

describe('the live stream', () => {
  it('reassembles a stream split at a random point', () => {
    const pepito = new Pepito();
    expect(pepito.state.state).toBe('unknown');

    pepito.feed(
      'data: {"event":"heartbeat","time":1725714568}\n\ndata: {"event":"pepi'
    );
    pepito.feed('to","type":"out","time":1725714575,"img":"a.jpg"}\n\n');

    expect(pepito.state.state).toBe('away');
    expect(pepito.state.since).toBe(1_725_714_575);
    pepito.close();
  });

  it('recognises a repeat after a reconnect without moving the counters', () => {
    const pepito = new Pepito();
    pepito.feed(
      'data: {"event":"pepito","type":"out","time":1725714575,"img":"a.jpg"}\n'
    );
    const duplicate = pepito.feed(
      'data: {"event":"pepito","type":"out","time":1725714575,"img":"a.jpg"}\n'
    );

    expect(duplicate[0]?.kind).toBe('duplicate');
    expect(pepito.state.count_out).toBe(1);
    pepito.close();
  });

  it('fires change only on real state changes', () => {
    const pepito = new Pepito();
    const changes: unknown[] = [];
    pepito.addEventListener('change', (event) => {
      changes.push(event.detail);
    });

    pepito.feed('data: {"event":"pepito","type":"out","time":1725714575}\n');
    pepito.feed('data: {"event":"pepito","type":"out","time":1725714600}\n');
    pepito.feed('data: {"event":"pepito","type":"in","time":1725715521}\n');

    expect(pepito.state.state).toBe('home');
    expect(changes).toHaveLength(2);
    pepito.close();
  });

  it('survives garbage', () => {
    const pepito = new Pepito();
    pepito.feed(': a comment\nevent: message\ndata: {invalid json}\n');

    expect(pepito.state.state).toBe('unknown');
    pepito.close();
  });
});

describe('the cache', () => {
  it('survives a process restart through a snapshot', () => {
    const pepito = new Pepito();
    pepito.feed('data: {"event":"pepito","type":"in","time":1725715521}\n');

    const revived = new Pepito(pepito.snapshot());

    expect(revived.state.state).toBe('home');
    expect(revived.state.since).toBe(1_725_715_521);
    expect(revived.isStale(10)).toBe(true);
    pepito.close();
    revived.close();
  });

  it('hands events back as plain JS objects', () => {
    const pepito = new Pepito();
    pepito.feed('data: {"event":"pepito","type":"in","time":1725715521}\n');

    expect(pepito.state.last?.way).toBe('in');
    expect(pepito.state.last?.img).toBeUndefined();
    pepito.close();
  });

  it('refuses to work after close()', () => {
    const pepito = new Pepito();
    pepito.close();

    expect(() => pepito.snapshot()).toThrow(/closed/);
  });
});

describe('your own fetch', () => {
  it('is what refresh() asks, and a bad status throws', async () => {
    const asked: string[] = [];
    const answers = [
      new Response('{"event":"pepito","type":"in","time":1725715521}'),
      new Response('', { status: 503 }),
    ];
    const pepito = new Pepito(undefined, {
      fetch: (input) => {
        asked.push(new Request(input).url);
        return Promise.resolve(answers.shift() as Response);
      },
    });

    await pepito.refresh();
    expect(asked).toEqual([REST_URL]);
    expect(pepito.state.state).toBe('home');

    await expect(pepito.refresh()).rejects.toMatchObject({ status: 503 });
    expect(pepito.state.state).toBe('home');
    pepito.close();
  });
});

describe('the core', () => {
  it('owns the addresses', () => {
    expect(SSE_URL.startsWith('https://')).toBe(true);
  });

  it('parses an empty archive into an array', () => {
    expect(Array.isArray(Pepito.historyParse('[]'))).toBe(true);
  });

  it('throws on an archive that is not one', () => {
    expect(() => Pepito.historyStats('{')).toThrow(/archive|JSON/i);
    expect(() => Pepito.historyStats('[{"way":"in"}]')).toThrow(/tweet 0/);
  });
});

// The same fixtures as crates/core/src/lib.rs, which the TS port has to agree with.
const TWEETS = `[
  {"full_text":"Pepito est sorti (12:47:41)","way":"out","created_at":"Sun Nov 13 10:47:15 +0000 2011","media":""},
  {"full_text":"Pepito est sorti (17:20:30)","way":"out","created_at":"Sun Nov 13 15:20:04 +0000 2011","media":""},
  {"full_text":"Pepito est rentre (17:30:30)","way":"in","created_at":"Sun Nov 13 15:30:04 +0000 2011","media":"m.jpg"},
  {"full_text":"broken","way":"sideways","created_at":"Sun Nov 13 15:30:04 +0000 2011","media":""},
  {"full_text":"wrong weekday","way":"in","created_at":"Mon Nov 13 15:30:04 +0000 2011"},
  {"full_text":"no such day","way":"in","created_at":"Wed Feb 30 15:30:04 +0000 2011"},
  {"full_text":"offset","way":"in","created_at":"Sun Nov 13 17:30:04 +0200 2011"}
]`;

const STREAM = [
  ': a comment\nevent: message\n',
  'data: {"event":"heartbeat","time":100}\r\n',
  'data: {"event":"pepito","type":"out","time":110,"img":"a.jpg"}\n',
  'data: {"event":"pepito","type":"out","time":110,"img":"a.jpg"}\n',
  'data: {"event":"pepito","type":"out","time":150,"img":null}\n',
  'data:{"event":"pepi',
  'to","type":"in","time":90}\n{"event":"heartbeat","time":50}\n',
  'data: {"event":"pepito","type":"sideways","time":1}\ndata: {"event":"pepito","type":"in","time":1.5}\n',
  'data: {"event":"pepito","type":"in","time":200,"img":7}\n',
  'data: {"event":"pepito","type":"in","time":200}\ndata: {invalid json}\n',
];

describe('the TS port', () => {
  it('pairs outings like the Rust core', () => {
    const stats = Pepito.historyStats(TWEETS);

    expect([stats.outs, stats.ins, stats.outings, stats.unpaired]).toEqual([
      2, 2, 1, 1,
    ]);
    expect(stats.longest?.secs).toBe(600);
    expect(stats.by_hour_out[10]).toBe(1);
    expect(stats.by_weekday_out[0]).toBe(2);
    expect(Pepito.historyParse(TWEETS)[0]?.time).toBe(1_321_181_235);
  });
});

const isWasmBuilt = existsSync(
  new URL('../src/wasm/pepito_bg.wasm', import.meta.url)
);

describe.skipIf(!isWasmBuilt)('the wasm build', () => {
  it('agrees with the TS port on the stream, state and snapshot', async () => {
    const wasm = await import('../src/wasm.js');
    await wasm.init();
    const pure = new Pepito();
    const rust = new wasm.Pepito();

    for (const chunk of STREAM) {
      expect(pure.feed(chunk)).toEqual(rust.feed(chunk));
      expect(pure.state).toEqual(rust.state);
    }
    expect(JSON.parse(pure.snapshot())).toEqual(JSON.parse(rust.snapshot()));
    // Snapshots move between backends.
    expect(new wasm.Pepito(pure.snapshot()).state).toEqual(pure.state);
    expect(new Pepito(rust.snapshot()).state).toEqual(rust.state);
    for (const garbage of ['', 'garbage', '{"count_in":-1,"count_out":0}']) {
      expect(new Pepito(garbage).state).toEqual(new wasm.Pepito(garbage).state);
    }
    rust.close();
  });

  it('agrees with the TS port on the archive', async () => {
    const wasm = await import('../src/wasm.js');
    await wasm.init();

    expect(Pepito.historyParse(TWEETS)).toEqual(
      wasm.Pepito.historyParse(TWEETS)
    );
    expect(Pepito.historyStats(TWEETS)).toEqual(
      wasm.Pepito.historyStats(TWEETS)
    );
    for (const broken of [
      '{',
      '[{"full_text":"","way":"in","created_at":"","media":null}]',
    ]) {
      expect(() => Pepito.historyStats(broken)).toThrow(
        wasm.InvalidArchiveError
      );
      expect(() => wasm.Pepito.historyStats(broken)).toThrow(
        wasm.InvalidArchiveError
      );
    }
    if (!archive) {
      return;
    }
    const json = readFileSync(archive, 'utf8');
    expect(Pepito.historyStats(json)).toEqual(wasm.Pepito.historyStats(json));
  });
});

const archive = ['tweets.json', '../tweets.json'].find((path) =>
  existsSync(path)
);

describe.skipIf(!archive)('the tweet archive', () => {
  it('pairs thousands of outings', () => {
    const stats = Pepito.historyStats(readFileSync(archive as string, 'utf8'));

    expect(stats.outings).toBeGreaterThan(1000);
    expect(stats.avg_outing_secs).toBeGreaterThan(0);
  });
});
