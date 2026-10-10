[![webDiplomacy Checks](https://github.com/oppiet30/webDiplomacy/actions/workflows/checks.yml/badge.svg?branch=master)](https://github.com/oppiet30/webDiplomacy/actions/workflows/checks.yml)

Diplomacy is a popular turn based strategy game in which you battle to control Europe; to win you must be diplomatic and strategic.
 
webDiplomacy lets you play Diplomacy online.

--- 

install/README.txt - Installation information.

AGPL.txt - The license webDiplomacy is distributed under.

---

We welcome code contributions for any of the issues on the "soon" milestone. Simply fork the project, and develop a fix in a branch. We accept pull requests that:

* are well tested
* only include one fix per pull request
* keep the code clean and maintainable
* use the same style as the rest of webdip
* keep whitespace changes to a minimum

When writing the text of your pull request, please include:

* The details of the testing that you've performed
* The github issue number that this pull request is a fix for

---

If you get errors for files within /javascript/ it is because some default Apache configurations use this as a shared folder by default. Disable this alias to resolve.

---

http://webdiplomacy.net/ - The official webDiplomacy server.

https://github.com/kestasjk/webDiplomacy - The webDiplomacy github source repository.

---

To get Philippe Paquette's MILA bots working with the base webDip docker install do:
Ensure that the IP address is the IP of the machine hosting docker (there is probably some docker context/network wizardry to do this..)

docker pull public.ecr.aws/n4k3z7o3/webdiplomacy:latest
docker run -d --env API_WEBDIPLOMACY=http://172.21.16.1:43000/api.php --env API_KEY_USER_01=bot1 --env API_KEY_USER_02=bot2 --env API_KEY_USER_03=bot3 --env API_KEY_USER_04=bot4 --env API_KEY_USER_05=bot5 --env API_KEY_USER_06=bot6 public.ecr.aws/n4k3z7o3/webdiplomacy:latest




Kestas J. Kuliukas - kestas@kuliukas.com

## GitHub-hosted checks

**webDiplomacy Checks** runs on GitHub's Ubuntu servers when commits reach `master`
or a pull request targets `master` in `oppiet30/webDiplomacy`. It can also be started
through **Actions → webDiplomacy Checks → Run workflow**. No local runner or Docker
installation on your computer is needed for this workflow.

The checks use `Dockerfile.runner` to install dependencies, lint application PHP,
run the React board smoke test, build the board, and check SSE JavaScript syntax.
They do not deploy the site or run database-backed DATC tests. GitHub-hosted jobs
start on fresh machines, so they build their own image. Logs and results are available
in the Actions tab and on pull requests.

```bash
gh workflow run checks.yml --repo oppiet30/webDiplomacy --ref master
```

## Local GitHub Actions runner (Linux)

The manually triggered **Local webDiplomacy Checks** workflow runs only in
`oppiet30/webDiplomacy` on `master`. It installs PHP dependencies, checks application
PHP syntax (excluding bundled third-party code in `contrib/`), runs the React board
smoke test, builds the board, and checks SSE JavaScript syntax. The checks run inside
Docker; generated files stay inside temporary containers. It does not start the
Docker Compose stack, publish ports, deploy a site, or run database-backed DATC tests.
The board build keeps the normal warning behavior; the test step uses `CI=true`.

Register a separate runner for this repository, even if the machine already runs a
runner for Secret Republic:

1. Install Docker and verify `docker info` works without `sudo` as your runner user.
2. Create a directory outside all repositories, such as `~/actions-runner-webdiplomacy`.
3. Open this repository's **Settings → Actions → Runners → New self-hosted runner**.
   Select Linux/x64 and run the displayed download and configuration commands in
   that directory. The registration token is temporary; do not commit it.
4. Accept the default runner group and work folder. Give the runner a recognizable
   name, such as `optiplex-3040-webdiplomacy`, and add the custom label `webdiplomacy`.
5. Start `./run.sh` and leave it running. For service operation, stop the foreground
   runner, then use `sudo ./svc.sh install "$(whoami)"` and `sudo ./svc.sh start`.
6. After merging the workflow into `master`, open **Actions → Local webDiplomacy
   Checks → Run workflow**, choose `master`, and start a new run.

Or start it from GitHub CLI:

```bash
gh workflow run local-tests.yml --repo oppiet30/webDiplomacy --ref master
```

The first build downloads PHP/Node images and dependencies; later builds can reuse
Docker's build cache. The job has a 60-minute limit. React compilation may use several
GB of RAM, so allow capacity for other applications on the runner. Temporary
containers and the run's image are removed; the build cache remains.

Dependency lockfiles are not tracked by this repository, so installs resolve the
currently available versions allowed by `composer.json` and `game-src/package.json`.
The workflow therefore uses `npm install`, not `npm ci`. The first runner execution
must still verify the image build and checks on the target machine.
