import { existsSync, readFileSync } from 'node:fs';

import { beforeAll, describe, expect, it } from 'vitest';

import { init, Pepito, REST_URL, SSE_URL } from '../src/pepito.js';

beforeAll(async () => {
  await init();
});

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
